<?php

namespace App\Http\Controllers;

use App\Services\ExcelWorkbook;
use App\Services\WorkspacePermissions;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class WorkQueueController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->attributes->get('is_admin'), 403);
    }

    public function query(Request $request): Builder
    {
        return app(WorkspacePermissions::class)->query($request, 'work_items');
    }

    public function preview(Request $request, ExcelWorkbook $excel): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['file' => 'required|file|max:10240|extensions:xlsx', 'sheet' => 'nullable|string|max:255', 'header_row' => 'nullable|integer|min:1|max:50']);
        $contents = base64_encode(file_get_contents($data['file']->getRealPath()));
        $result = $excel->run(['action' => 'read', 'contents' => $contents, 'sheet' => $data['sheet'] ?? null, 'header_row' => $data['header_row'] ?? 1]);
        $checksum = hash('sha256', base64_decode($contents));
        $import = DB::table('spreadsheet_imports')->where('office_id', $request->attributes->get('office_id'))->where('checksum', $checksum)->first();
        abort_if($import && $import->mapping, 409, 'Esta planilha já foi importada. Baixe a versão atualizada para evitar duplicação.');
        $id = $import?->id ?? DB::table('spreadsheet_imports')->insertGetId(['office_id' => $request->attributes->get('office_id'), 'filename' => $data['file']->getClientOriginalName(), 'checksum' => $checksum, 'contents' => $contents, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['id' => $id, 'sheet' => $result['sheet'], 'sheets' => $result['sheets'], 'headers' => $result['headers'], 'rows' => array_slice($result['rows'], 0, 5), 'total' => count($result['rows'])]);
    }

    public function import(Request $request, int $id, ExcelWorkbook $excel): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['sheet' => 'required|string|max:255', 'header_row' => 'required|integer|min:1|max:50',
            'mapping.title' => 'required|regex:/^[A-Z]{1,3}$/', 'mapping.due_at' => 'required|regex:/^[A-Z]{1,3}$/',
            'mapping.process_number' => 'nullable|regex:/^[A-Z]{1,3}$/', 'mapping.context' => 'nullable|regex:/^[A-Z]{1,3}$/',
            'assigned_member_id' => ['required', Rule::exists('members', 'id')->where('office_id', $request->attributes->get('office_id'))->where('active', true)]]);
        $import = DB::table('spreadsheet_imports')->where('office_id', $request->attributes->get('office_id'))->find($id);
        abort_unless($import, 404);
        $read = $excel->run(['action' => 'read', 'contents' => $import->contents, 'sheet' => $data['sheet'], 'header_row' => $data['header_row']]);
        $items = [];
        foreach ($read['rows'] as $row) {
            $v = $row['values'];
            $title = trim($v[$data['mapping']['title']] ?? '');
            if (! $title) {
                continue;
            }
            $date = trim($v[$data['mapping']['due_at']] ?? '');
            try {
                if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
                    $due = Carbon::createFromFormat('!d/m/Y', $date)->endOfDay();
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}(T|\s|$)/', $date)) {
                    $due = Carbon::parse($date);
                    if (strlen($date) === 10) {
                        $due->endOfDay();
                    }
                } else {
                    throw new \RuntimeException;
                }
            } catch (\Throwable) {
                throw ValidationException::withMessages(['mapping' => "Linha {$row['row']}: informe uma data válida (dd/mm/aaaa ou data do Excel)."]);
            }
            if (mb_strlen($title) > 500) {
                throw ValidationException::withMessages(['mapping' => "Linha {$row['row']}: obrigação maior que 500 caracteres."]);
            }
            $items[] = ['office_id' => $import->office_id, 'spreadsheet_import_id' => $id, 'source_row' => $row['row'], 'assigned_member_id' => $data['assigned_member_id'], 'title' => $title, 'due_at' => $due,
                'process_number' => mb_substr($v[$data['mapping']['process_number'] ?? ''] ?? '', 0, 255), 'context' => mb_substr($v[$data['mapping']['context'] ?? ''] ?? '', 0, 10000), 'created_at' => now(), 'updated_at' => now()];
        }
        if (! $items) {
            throw ValidationException::withMessages(['mapping' => 'Nenhuma obrigação encontrada. Confira a aba e as colunas.']);
        }
        DB::transaction(function () use ($id, $data, $items): void {
            $locked = DB::table('spreadsheet_imports')->where('id', $id)->lockForUpdate()->first();
            abort_if($locked->mapping, 409, 'Esta planilha já foi importada.');
            DB::table('spreadsheet_imports')->where('id', $id)->update(['mapping' => json_encode($data['mapping']), 'sheet' => $data['sheet'], 'header_row' => $data['header_row'], 'updated_at' => now()]);
            foreach (array_chunk($items, 100) as $chunk) {
                DB::table('work_items')->insert($chunk);
            }
        });

        return response()->json(['count' => count($items)]);
    }

    public function detail(Request $request, int $id): JsonResponse
    {
        $item = $this->query($request)->find($id);
        abort_unless($item, 404);

        return response()->json(['item' => $item, 'history' => DB::table('work_item_updates')->where('work_item_id', $id)->orderByDesc('id')->get()]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        abort_unless(app(WorkspacePermissions::class)->allows($request, 'prazos.update'), 403);
        $data = $request->validate(['version' => 'required|integer|min:1', 'status' => ['required', Rule::in(['Pendente', 'Em andamento', 'Concluído'])], 'note' => 'required|string|max:10000']);
        DB::transaction(function () use ($request, $id, $data): void {
            $item = $this->query($request)->lockForUpdate()->find($id);
            abort_unless($item, 404);
            abort_unless($item->version === $data['version'], 409, 'Este prazo foi alterado por outra pessoa. Reabra para conferir a versão atual.');
            DB::table('work_items')->where('id', $id)->update(['status' => $data['status'], 'note' => $data['note'], 'version' => $item->version + 1, 'completed_at' => $data['status'] === 'Concluído' ? now() : null, 'updated_at' => now()]);
            DB::table('work_item_updates')->insert(['work_item_id' => $id, 'member_id' => $request->attributes->get('member_id'), 'actor' => $request->attributes->get('actor'), 'status' => $data['status'], 'note' => $data['note'], 'created_at' => now()]);
        });

        return response()->json(['ok' => true]);
    }

    public function assign(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['ids' => 'required|array|min:1|max:10000', 'ids.*' => 'required|integer|distinct', 'member_id' => ['required', Rule::exists('members', 'id')->where('office_id', $request->attributes->get('office_id'))->where('active', true)]]);
        DB::transaction(function () use ($request, $data): void {
            $items = $this->query($request)->whereIn('id', $data['ids'])->lockForUpdate()->get();
            abort_unless($items->count() === count($data['ids']), 404);
            $member = DB::table('members')->find($data['member_id']);
            foreach ($items as $item) {
                DB::table('work_items')->where('id', $item->id)->update(['assigned_member_id' => $member->id, 'version' => $item->version + 1, 'updated_at' => now()]);
                DB::table('work_item_updates')->insert(['work_item_id' => $item->id, 'member_id' => $request->attributes->get('member_id'), 'actor' => $request->attributes->get('actor'), 'status' => $item->status, 'note' => 'Responsável alterado para '.$member->name, 'created_at' => now()]);
            }
        });

        return response()->json(['ok' => true]);
    }

    public function export(Request $request, int $id, ExcelWorkbook $excel): Response
    {
        $this->admin($request);
        $import = DB::table('spreadsheet_imports')->where('office_id', $request->attributes->get('office_id'))->find($id);
        abort_unless($import && $import->mapping, 404);
        $items = DB::table('work_items')->leftJoin('members', 'members.id', '=', 'work_items.assigned_member_id')->where('spreadsheet_import_id', $id)
            ->select('work_items.source_row', 'work_items.status', 'work_items.note', 'work_items.updated_at as updated', 'members.name as responsible')->get()->map(fn ($v) => (array) $v)->all();
        $out = $excel->run(['action' => 'export', 'contents' => $import->contents, 'sheet' => $import->sheet, 'header_row' => $import->header_row, 'items' => $items]);

        return response(base64_decode($out['contents']))->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', 'attachment; filename="prazos-atualizados-'.$id.'.xlsx"')->header('Cache-Control', 'private, no-store')->header('X-Content-Type-Options', 'nosniff');
    }
}
