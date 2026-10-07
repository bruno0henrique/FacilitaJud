<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeetingController extends Controller
{
    public const CONSENT_VERSION = '2026-10-07';

    private function access(Request $request, bool $record = false): void
    {
        $access = app(WorkspacePermissions::class);
        abort_unless($access->allows($request, 'reunioes.view'), 403);
        if ($record) {
            abort_unless($access->allows($request, 'reunioes.record'), 403);
        }
    }

    public function appointments(Request $request): Builder
    {
        return DB::table('appointments')->where('office_id', $request->attributes->get('office_id'))->where('kind', 'Reunião')
            ->when(! $request->attributes->get('is_admin'), fn ($q) => $q->where('assigned_member_id', $request->attributes->get('member_id')));
    }

    public function consented(Request $request): bool
    {
        return DB::table('meeting_consents')->where('member_id', $request->attributes->get('member_id'))->where('office_id', $request->attributes->get('office_id'))->where('version', self::CONSENT_VERSION)->exists();
    }

    public function consent(Request $request): JsonResponse
    {
        $this->access($request);
        $request->validate(['accepted' => 'required|accepted']);
        DB::table('meeting_consents')->insertOrIgnore(['office_id' => $request->attributes->get('office_id'), 'member_id' => $request->attributes->get('member_id'), 'version' => self::CONSENT_VERSION, 'accepted_at' => now()]);

        return response()->json(['message' => 'Ciência registrada.']);
    }

    private function recording(Request $request, int $id): object
    {
        $this->access($request);
        $row = DB::table('meeting_recordings')->where('office_id', $request->attributes->get('office_id'))->where('id', $id)
            ->whereIn('appointment_id', $this->appointments($request)->select('id'))->first();
        abort_unless($row, 404);

        return $row;
    }

    private function recorder(Request $request, object $row): void
    {
        $this->access($request, true);
        abort_unless($row->member_id === $request->attributes->get('member_id'), 403, 'Somente quem iniciou pode finalizar esta gravação.');
    }

    public function start(Request $request, int $id): JsonResponse
    {
        $this->access($request, true);
        abort_unless($this->consented($request), 403, 'Leia e aceite os termos de gravação primeiro.');
        $request->validate(['participants_confirmed' => 'required|accepted', 'mime' => ['required', Rule::in(['audio/webm', 'audio/webm;codecs=opus', 'audio/ogg', 'audio/ogg;codecs=opus', 'audio/mp4', 'audio/mp4;codecs=mp4a.40.2'])]]);
        $recording = DB::transaction(function () use ($request, $id): int {
            $appointment = $this->appointments($request)->where('id', $id)->lockForUpdate()->first();
            abort_unless($appointment, 404);
            abort_if(DB::table('meeting_recordings')->where('appointment_id', $id)->where('status', 'recording')->where('updated_at', '>', now()->subMinutes(10))->exists(), 409, 'Esta reunião já está sendo gravada.');
            DB::table('meeting_recordings')->where('appointment_id', $id)->where('status', 'recording')->update(['status' => 'interrupted', 'updated_at' => now()]);
            $recording = DB::table('meeting_recordings')->insertGetId(['office_id' => $request->attributes->get('office_id'), 'appointment_id' => $id, 'member_id' => $request->attributes->get('member_id'), 'mime' => $request->input('mime'), 'status' => 'recording', 'consent_version' => self::CONSENT_VERSION, 'participants_confirmed_at' => now(), 'started_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            DB::table('activities')->insert(['office_id' => $request->attributes->get('office_id'), 'description' => 'Gravação iniciada: '.$appointment->title, 'actor' => $request->attributes->get('actor'), 'kind' => 'meeting', 'created_at' => now()]);

            return $recording;
        });

        return response()->json(['id' => $recording], 201);
    }

    public function chunk(Request $request, int $id): JsonResponse
    {
        $row = $this->recording($request, $id);
        $this->recorder($request, $row);
        $data = $request->validate(['sequence' => 'required|integer|min:0|max:100000', 'file' => 'required|file|max:512']);
        $contents = $request->file('file')->getContent();
        abort_if($contents === '', 422, 'Trecho de áudio vazio.');
        $checksum = hash('sha256', $contents);
        DB::transaction(function () use ($id, $data, $contents, $checksum): void {
            $row = DB::table('meeting_recordings')->where('id', $id)->lockForUpdate()->first();
            $existing = DB::table('meeting_audio_chunks')->where('recording_id', $id)->where('sequence', $data['sequence'])->first(['checksum']);
            if ($existing) {
                abort_unless(hash_equals($existing->checksum, $checksum), 409, 'Este trecho já foi salvo com outro conteúdo.');

                return;
            }
            abort_unless($row->status === 'recording', 409, 'Esta gravação já foi encerrada.');
            $count = DB::table('meeting_audio_chunks')->where('recording_id', $id)->count();
            abort_unless($data['sequence'] === $count, 409, 'Envie os trechos na ordem da gravação.');
            abort_if($row->size + strlen($contents) > 268435456, 422, 'O áudio atingiu o limite de armazenamento de 256 MB por gravação. Encerre e inicie outra gravação.');
            DB::table('meeting_audio_chunks')->insert(['recording_id' => $id, 'sequence' => $data['sequence'], 'size' => strlen($contents), 'checksum' => $checksum, 'contents' => base64_encode($contents)]);
            DB::table('meeting_recordings')->where('id', $id)->update(['size' => $row->size + strlen($contents), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Trecho salvo.']);
    }

    public function finish(Request $request, int $id): JsonResponse
    {
        $row = $this->recording($request, $id);
        $this->recorder($request, $row);
        $data = $request->validate(['duration_seconds' => 'required|integer|min:0|max:604800', 'chunks' => 'required|integer|min:0|max:100001']);
        DB::transaction(function () use ($id, $data): void {
            $row = DB::table('meeting_recordings')->where('id', $id)->lockForUpdate()->first();
            abort_unless(DB::table('meeting_audio_chunks')->where('recording_id', $id)->count() === $data['chunks'], 409, 'Ainda há trechos de áudio que precisam ser salvos.');
            DB::table('meeting_recordings')->where('id', $id)->update(['status' => $row->size ? 'ready' : 'empty', 'duration_seconds' => $data['duration_seconds'], 'finished_at' => now(), 'updated_at' => now()]);
        });

        return response()->json(['message' => 'Gravação salva.']);
    }

    public function notes(Request $request, int $id): JsonResponse
    {
        $this->recording($request, $id);
        $this->access($request, true);
        $data = $request->validate(['notes' => 'nullable|string|max:30000', 'minutes' => 'nullable|string|max:50000']);
        DB::table('meeting_recordings')->where('id', $id)->update($data + ['updated_at' => now()]);

        return response()->json(['message' => 'Anotações e ata salvas.']);
    }

    public function audio(Request $request, int $id): StreamedResponse
    {
        $row = $this->recording($request, $id);
        abort_unless($row->size > 0, 404);
        $size = (int) $row->size;
        $start = 0;
        $end = $size - 1;
        $status = 200;
        if ($range = $request->header('Range')) {
            abort_unless(preg_match('/^bytes=(\d*)-(\d*)$/', $range, $matches) && ($matches[1] !== '' || $matches[2] !== ''), 416);
            if ($matches[1] === '') {
                $start = max(0, $size - (int) $matches[2]);
            } else {
                $start = (int) $matches[1];
                $end = $matches[2] === '' ? $end : min($end, (int) $matches[2]);
            }
            abort_unless($start <= $end && $start < $size, 416);
            $status = 206;
        }
        $headers = ['Content-Type' => $row->mime, 'Content-Length' => $end - $start + 1, 'Accept-Ranges' => 'bytes', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];
        if ($status === 206) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        return response()->stream(function () use ($id, $start, $end): void {
            $offset = 0;
            foreach (DB::table('meeting_audio_chunks')->where('recording_id', $id)->orderBy('sequence')->get(['id', 'size']) as $chunk) {
                $chunkEnd = $offset + $chunk->size - 1;
                if ($chunkEnd >= $start && $offset <= $end) {
                    $contents = base64_decode(DB::table('meeting_audio_chunks')->where('id', $chunk->id)->value('contents'), true);
                    echo substr($contents, max(0, $start - $offset), min($chunkEnd, $end) - max($offset, $start) + 1);
                }
                $offset += $chunk->size;
                if ($offset > $end) {
                    break;
                }
            }
        }, $status, $headers);
    }
}
