<?php

namespace Tests\Feature;

use App\Http\Controllers\MeetingController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeetingRecordingTest extends TestCase
{
    use RefreshDatabase;

    private int $office;

    private int $meeting;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => true]);
        $this->seed();
        $this->office = DB::table('offices')->where('is_demo', true)->value('id');
        $this->meeting = DB::table('appointments')->where('office_id', $this->office)->where('kind', 'Reunião')->value('id');
    }

    private function start(): int
    {
        $this->postJson('/api/v1/meetings/consent', ['accepted' => true])->assertOk();

        return $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', ['participants_confirmed' => true, 'mime' => 'audio/webm;codecs=opus'])->assertCreated()->json('id');
    }

    private function chunk(int $recording, int $sequence, string $contents): void
    {
        $this->post('/api/v1/meeting-recordings/'.$recording.'/chunks', ['sequence' => (string) $sequence, 'file' => UploadedFile::fake()->createWithContent('audio.webm', $contents)], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_meetings_follow_agenda_events_and_only_include_meeting_type(): void
    {
        $meeting = DB::table('appointments')->find($this->meeting);
        $hearing = DB::table('appointments')->where('kind', 'Audiência')->first();
        $this->get('/reunioes')->assertOk()->assertSee($meeting->title)->assertDontSee($hearing->title)->assertSee('Termos de uso da gravação')->assertViewHas('meetingConsented', false);
        DB::table('appointments')->where('id', $this->meeting)->update(['kind' => 'Atendimento']);
        $this->get('/reunioes')->assertOk()->assertDontSee($meeting->title);
    }

    public function test_consent_is_required_once_and_participants_must_be_confirmed_each_time(): void
    {
        $payload = ['participants_confirmed' => true, 'participants' => ['Participante de teste'], 'mime' => 'audio/webm'];
        $this->get('/reunioes/gravar/'.$this->meeting)->assertForbidden();
        $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', $payload)->assertForbidden();
        $this->postJson('/api/v1/meetings/consent', ['accepted' => false])->assertUnprocessable();
        $this->postJson('/api/v1/meetings/consent', ['accepted' => true])->assertOk();
        $this->postJson('/api/v1/meetings/consent', ['accepted' => true])->assertOk();
        $this->assertDatabaseCount('meeting_consents', 1);
        $this->get('/reunioes')->assertViewHas('meetingConsented', true);
        $this->get('/configuracoes')->assertOk()->assertSee('Ciência registrada no seu perfil');
        $this->get('/reunioes/gravar/'.$this->meeting)->assertOk()->assertSee('meeting-recording-window', false)->assertDontSee('id="sidebar"', false)->assertSee('Todos os envolvidos');
        $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', ['mime' => 'audio/webm'])->assertUnprocessable();
        $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', $payload)->assertCreated()->assertJsonStructure(['id']);
        $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', $payload)->assertConflict();
        $this->assertDatabaseHas('meeting_recordings', ['consent_version' => MeetingController::CONSENT_VERSION, 'participants' => json_encode(['Participante de teste'])]);
    }

    public function test_audio_chunks_are_idempotent_ordered_and_stream_with_byte_ranges(): void
    {
        $id = $this->start();
        $this->chunk($id, 0, 'header123');
        $this->chunk($id, 0, 'header123');
        $this->assertDatabaseCount('meeting_audio_chunks', 1);
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => 0, 'file' => UploadedFile::fake()->createWithContent('audio.webm', 'changed')], ['Accept' => 'application/json'])->assertConflict();
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => 2, 'file' => UploadedFile::fake()->createWithContent('audio.webm', 'out of order')], ['Accept' => 'application/json'])->assertConflict();
        $this->chunk($id, 1, 'body456');
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 14400, 'chunks' => 3])->assertConflict();
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 14400, 'chunks' => '2'])->assertOk();
        $this->assertDatabaseHas('meeting_recordings', ['id' => $id, 'size' => 16, 'duration_seconds' => 14400, 'status' => 'ready']);
        $audio = $this->get('/reunioes/audio/'.$id)->assertOk()->assertHeader('Accept-Ranges', 'bytes')->assertHeader('Content-Length', '16');
        $this->assertSame('header123body456', $audio->streamedContent());
        $range = $this->get('/reunioes/audio/'.$id, ['Range' => 'bytes=7-11'])->assertStatus(206)->assertHeader('Content-Range', 'bytes 7-11/16');
        $this->assertSame('23bod', $range->streamedContent());
        $tail = $this->get('/reunioes/audio/'.$id, ['Range' => 'bytes=-3'])->assertStatus(206);
        $this->assertSame('456', $tail->streamedContent());
        $this->get('/reunioes/audio/'.$id, ['Range' => 'bytes=999-'])->assertStatus(416);
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => 2, 'file' => UploadedFile::fake()->createWithContent('audio.webm', 'late')], ['Accept' => 'application/json'])->assertConflict();
    }

    public function test_notes_are_saved_without_modifying_legal_deadlines(): void
    {
        $id = $this->start();
        $before = DB::table('deadlines')->get()->toJson();
        $this->patchJson('/api/v1/meeting-recordings/'.$id.'/notes', ['notes' => 'Decisões revisadas', 'minutes' => 'Ata registrada manualmente'])->assertOk();
        $this->assertDatabaseHas('meeting_recordings', ['id' => $id, 'minutes' => 'Ata registrada manualmente']);
        $this->assertSame($before, DB::table('deadlines')->get()->toJson());
        $this->get('/reunioes')->assertOk()->assertSee('Ata registrada manualmente')->assertSee('Em desenvolvimento');
    }

    public function test_other_offices_and_unassigned_associates_cannot_access_audio(): void
    {
        $id = $this->start();
        $this->chunk($id, 0, 'privateaudio');
        $otherOffice = DB::table('offices')->insertGetId(['name' => 'Outro', 'display_name' => 'Outro']);
        $foreign = DB::table('appointments')->insertGetId(['office_id' => $otherOffice, 'title' => 'Privada', 'kind' => 'Reunião', 'location' => 'Sala', 'starts_at' => now()]);
        $this->postJson('/api/v1/meetings/'.$foreign.'/recordings', ['participants_confirmed' => true, 'mime' => 'audio/webm'])->assertNotFound();
        $member = DB::table('members')->where('office_id', $this->office)->where('account_type', 'associate')->first();
        DB::table('members')->where('id', $member->id)->update(['provider_id' => 'meeting-associate', 'permissions' => json_encode(['reunioes.view'])]);
        $this->withSession(['identity' => ['id' => 'meeting-associate', 'expires_at' => time() + 900]]);
        $this->get('/reunioes')->assertOk()->assertDontSee('data-record-meeting=', false);
        $this->get('/reunioes/audio/'.$id)->assertNotFound();
        DB::table('appointments')->where('id', $this->meeting)->update(['assigned_member_id' => $member->id]);
        $this->get('/reunioes/audio/'.$id)->assertOk();
        $this->patchJson('/api/v1/meeting-recordings/'.$id.'/notes', ['notes' => 'Não autorizado'])->assertForbidden();
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 5, 'chunks' => 1])->assertForbidden();
        $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', ['participants_confirmed' => true, 'mime' => 'audio/webm'])->assertForbidden();
    }

    public function test_stale_recordings_keep_saved_audio_when_a_new_session_starts(): void
    {
        $id = $this->start();
        $this->chunk($id, 0, 'partial');
        DB::table('meeting_recordings')->where('id', $id)->update(['updated_at' => now()->subMinutes(11)]);
        $next = $this->postJson('/api/v1/meetings/'.$this->meeting.'/recordings', ['participants_confirmed' => true, 'mime' => 'audio/webm'])->assertCreated()->json('id');
        $this->assertNotSame($id, $next);
        $this->assertDatabaseHas('meeting_recordings', ['id' => $id, 'status' => 'interrupted', 'size' => 7]);
        $this->get('/reunioes/audio/'.$id)->assertOk();
    }

    public function test_real_opus_audio_is_preserved_across_chunk_upload_and_playback(): void
    {
        $id = $this->start();
        $audio = file_get_contents(base_path('tests/Fixtures/meeting-tone.webm'));
        $this->assertStringStartsWith(hex2bin('1a45dfa3'), $audio);
        $this->chunk($id, 0, substr($audio, 0, 1000));
        $this->chunk($id, 1, substr($audio, 1000));
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 1, 'chunks' => '2'])->assertOk();
        $response = $this->get('/reunioes/audio/'.$id)->assertOk()->assertHeader('Content-Type', 'audio/webm;codecs=opus');
        $this->assertSame($audio, $response->streamedContent());
        $this->get('/reunioes')->assertOk()->assertSee('audio controls', false)->assertSee('Baixar áudio');
    }

    public function test_saved_recordings_are_discoverable_and_finalization_is_idempotent(): void
    {
        $id = $this->start();
        $this->chunk($id, 0, 'synthetic-audio');
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 8, 'chunks' => 1])->assertOk()->assertJson(['id' => $id, 'status' => 'ready', 'size' => 15])->assertJsonPath('audio_url', route('meetings.audio', ['id' => $id]));
        $activityCount = DB::table('activities')->count();
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 99, 'chunks' => 1])->assertOk();
        $this->assertSame($activityCount, DB::table('activities')->count());
        $this->assertDatabaseHas('meeting_recordings', ['id' => $id, 'duration_seconds' => 8]);
        $list = $this->getJson('/api/v1/meetings/'.$this->meeting.'/recordings')->assertOk()->assertJsonPath('count', 1);
        $this->assertStringContainsString('audio controls', $list->json('html'));
        $this->assertStringContainsString('Resumo e ata por IA', $list->json('html'));
        $this->assertStringContainsString('Em desenvolvimento', $list->json('html'));
        $this->assertStringNotContainsString(base64_encode('synthetic-audio'), $list->getContent());
        $this->get('/reunioes?meeting='.$this->meeting)->assertOk()->assertSee('data-meeting-id="'.$this->meeting.'"  open', false);
    }

    public function test_empty_capture_is_reported_and_cannot_be_played(): void
    {
        $id = $this->start();
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 1, 'chunks' => 0])->assertOk()->assertJson(['status' => 'empty', 'size' => 0, 'audio_url' => null, 'message' => 'Nenhum áudio foi capturado.']);
        $this->get('/reunioes/audio/'.$id)->assertNotFound();
    }

    public function test_recording_list_and_mutations_enforce_assignment_and_session(): void
    {
        $id = $this->start();
        $member = DB::table('members')->where('office_id', $this->office)->where('account_type', 'associate')->first();
        DB::table('members')->where('id', $member->id)->update(['provider_id' => 'secure-meeting-user', 'permissions' => json_encode(['reunioes.view', 'reunioes.record'])]);
        $this->withSession(['identity' => ['id' => 'secure-meeting-user', 'expires_at' => time() + 900]]);
        $this->getJson('/api/v1/meetings/'.$this->meeting.'/recordings')->assertNotFound();
        DB::table('appointments')->where('id', $this->meeting)->update(['assigned_member_id' => $member->id]);
        $this->getJson('/api/v1/meetings/'.$this->meeting.'/recordings')->assertOk();
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => '0', 'file' => UploadedFile::fake()->createWithContent('audio.webm', 'injected')], ['Accept' => 'application/json'])->assertForbidden();
        $this->postJson('/api/v1/meeting-recordings/'.$id.'/finish', ['duration_seconds' => 1, 'chunks' => 0])->assertForbidden();
        config(['facilitajud.demo' => false]);
        $this->withSession(['identity' => ['id' => 'secure-meeting-user', 'expires_at' => time() - 1]]);
        $this->getJson('/api/v1/meetings/'.$this->meeting.'/recordings')->assertUnauthorized();
        $this->get('/reunioes/audio/'.$id)->assertRedirect('/entrar');
    }

    public function test_untrusted_notes_are_escaped_and_invalid_uploads_are_rejected(): void
    {
        $id = $this->start();
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => '-1', 'file' => UploadedFile::fake()->createWithContent('audio.webm', 'invalid')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post('/api/v1/meeting-recordings/'.$id.'/chunks', ['sequence' => '0', 'file' => UploadedFile::fake()->create('large.webm', 513)], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertDatabaseCount('meeting_audio_chunks', 0);
        $this->patchJson('/api/v1/meeting-recordings/'.$id.'/notes', ['notes' => '</textarea><script>alert(1)</script>', 'minutes' => '<img src=x onerror=alert(1)>'])->assertOk();
        $html = $this->getJson('/api/v1/meetings/'.$this->meeting.'/recordings')->assertOk()->json('html');
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;img', $html);
    }

    public function test_csrf_protection_blocks_writes_without_a_session_token(): void
    {
        $this->app->instance('env', 'production');
        try {
            $this->postJson('/api/v1/meeting-recordings/999999/finish', ['duration_seconds' => 1, 'chunks' => 0])->assertStatus(419);
            $this->patchJson('/api/v1/meeting-recordings/999999/notes', ['notes' => 'forged'])->assertStatus(419);
            $this->postJson('/api/v1/team/invite', [])->assertStatus(419);
            $this->postJson('/api/v1/neo/chat', ['message' => 'Explique custas'])->assertStatus(419);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }
}
