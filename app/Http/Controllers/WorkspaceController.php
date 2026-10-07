<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Task;
use App\Services\Dashboard;
use App\Services\WorkspacePermissions;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceController extends Controller
{
    public const MODULES = [
        'painel' => ['Painel', 'Sua rotina, em equilíbrio.', 'layout-dashboard'],
        'tarefas' => ['Tarefas', 'Cada tarefa no seu tempo.', 'list-checks'],
        'processos' => ['Processos', 'Seus processos, sempre em perspectiva.', 'scale'],
        'agenda' => ['Agenda', 'Um lugar para cada compromisso.', 'calendar-days'],
        'reunioes' => ['Reuniões', 'Conversas registradas. Próximos passos claros.', 'users-round'],
        'prazos' => ['Prazos', 'Antecipe o que precisa de atenção.', 'clock-3'],
        'clientes' => ['Clientes', 'Informações próximas de quem importa.', 'users-round'],
        'documentos' => ['Documentos', 'Cada arquivo no lugar certo.', 'files'],
        'equipe' => ['Equipe', 'Responsabilidades claras. Rotina compartilhada.', 'contact-round'],
        'mensagens' => ['Mensagens', 'Conversas do seu escritório.', 'mail'],
        'configuracoes' => ['Configurações', 'Os detalhes do seu espaço de trabalho.', 'settings-2'],
    ];

    public function index(Request $request, Dashboard $dashboard, string $module = 'painel'): View
    {
        abort_unless(isset(self::MODULES[$module]), 404);
        $officeId = (int) $request->attributes->get('office_id');
        $access = app(WorkspacePermissions::class);
        abort_unless($access->module($request, $module), 403);
        $data = $dashboard->data($officeId, $request, $module);
        if (! $request->attributes->get('is_admin')) {
            $data['deadlines'] = collect();
            $data['dueTodayCount'] = 0;
            $data['overdueCount'] = 0;
        }
        $queue = app(WorkQueueController::class)->query($request);
        $queueNext = $module === 'painel' ? (clone $queue)->where('status', '!=', 'Concluído')->orderBy('due_at')->orderBy('id')->first() : null;
        $primaryWork = $queueNext && (! $data['nextTask'] || ! $request->attributes->get('is_admin') || Carbon::parse($queueNext->due_at)->lte($data['nextTask']->due_at)) ? $queueNext : null;
        $day = $request->query('day', now()->toDateString());
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
            $day = now()->toDateString();
        }
        try {
            Carbon::parse($day);
        } catch (\Throwable) {
            $day = now()->toDateString();
        }
        $daily = (clone $queue)->whereBetween('due_at', [Carbon::parse($day)->startOfDay(), Carbon::parse($day)->endOfDay()]);
        $workCounts = $module === 'prazos' ? (clone $daily)->selectRaw("COUNT(*) as total, SUM(CASE WHEN status = 'Concluído' THEN 1 ELSE 0 END) as completed")->first() : null;
        $workTotal = (int) ($workCounts->total ?? 0);
        $workCompleted = (int) ($workCounts->completed ?? 0);
        $workItems = $module === 'prazos' ? $daily->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('q'), fn ($q, $search) => $q->where(function ($q) use ($search): void {
                $q->where('title', 'like', '%'.$search.'%')->orWhere('process_number', 'like', '%'.$search.'%');
            }))
            ->orderBy('due_at')->orderBy('id')->paginate(25)->withQueryString() : null;
        $data['queueTodayCount'] = $module === 'painel' ? (clone $queue)->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->where('status', '!=', 'Concluído')->count() : 0;
        $clientQuery = $access->query($request, 'clients');
        if ($module === 'mensagens') {
            $clientQuery = $this->messageClients($request);
        }
        $clients = $module === 'clientes' ? $clientQuery->when($request->query('q'), fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))->orderBy('name')->paginate(15)->withQueryString()
            : (in_array($module, ['processos', 'mensagens'], true) ? $clientQuery->orderBy('name')->get() : collect());
        $caseQuery = $module === 'documentos' && $access->allows($request, 'documentos.upload') ? DB::table('legal_cases')->where('legal_cases.office_id', $officeId)->when(! $request->attributes->get('is_admin'), fn ($q) => $q->where('assigned_member_id', $request->attributes->get('member_id'))) : $access->query($request, 'legal_cases');
        $caseList = in_array($module, ['painel', 'tarefas', 'processos', 'clientes', 'agenda', 'reunioes', 'prazos', 'documentos'], true) ? $caseQuery->join('clients', 'clients.id', '=', 'legal_cases.client_id')
            ->where('legal_cases.office_id', $officeId)->select('legal_cases.*', 'clients.name as client_name')->orderBy('legal_cases.id') : null;
        $cases = $caseList ? ($module === 'processos' ? $caseList->when($request->query('q'), fn ($q, $search) => $q->where(fn ($q) => $q->where('legal_cases.title', 'like', '%'.$search.'%')->orWhere('legal_cases.number', 'like', '%'.$search.'%')->orWhere('clients.name', 'like', '%'.$search.'%')))->paginate(25)->withQueryString() : $caseList->get()) : collect();
        $calendarMonth = now()->startOfMonth();
        if ($module === 'agenda') {
            $request->validate(['month' => 'nullable|date_format:Y-m']);
            $calendarMonth = $request->filled('month') ? Carbon::createFromFormat('Y-m', $request->query('month'))->startOfMonth() : now()->startOfMonth();
            $data['appointments'] = $access->query($request, 'appointments')->whereBetween('starts_at', [$calendarMonth->copy()->startOfWeek(Carbon::MONDAY), $calendarMonth->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY)->endOfDay()])->orderBy('starts_at')->get();
        }

        if ($module === 'reunioes') {
            $meeting = app(MeetingController::class);
            $data['meetings'] = $meeting->appointments($request)->orderByRaw('CASE WHEN starts_at >= ? THEN 0 ELSE 1 END', [now()->startOfDay()])->orderByRaw('CASE WHEN starts_at >= ? THEN starts_at END ASC', [now()->startOfDay()])->orderByDesc('starts_at')->paginate(15)->withQueryString();
            $data['meetingRecordings'] = DB::table('meeting_recordings')->where('office_id', $officeId)->whereIn('appointment_id', $data['meetings']->pluck('id'))->orderByDesc('id')->get()->groupBy('appointment_id');
            $data['meetingConsented'] = $meeting->consented($request);
            $data['meetingConsentVersion'] = MeetingController::CONSENT_VERSION;
        }

        return view('workspace', $data + [
            'module' => $module, 'modules' => array_filter(self::MODULES, fn ($value, $key) => $access->module($request, $key), ARRAY_FILTER_USE_BOTH),
            'access' => $access, 'actor' => $request->attributes->get('actor'), 'permissionOptions' => WorkspacePermissions::OPTIONS,
            'categories' => in_array($module, ['equipe', 'configuracoes'], true) ? DB::table('team_categories')->where('office_id', $officeId)->when(! $request->attributes->get('is_admin'), fn ($q) => $q->whereIn('id', DB::table('members')->where('id', $request->attributes->get('member_id'))->select('category_id')))->orderBy('name')->get() : collect(),
            'calendarMonth' => $calendarMonth,
            'meetingProfileConsent' => $module === 'configuracoes' ? DB::table('meeting_consents')->where('office_id', $officeId)->where('member_id', $request->attributes->get('member_id'))->where('version', MeetingController::CONSENT_VERSION)->first() : null,
            'isAdmin' => $request->attributes->get('is_admin'), 'workItems' => $workItems, 'workTotal' => $workTotal,
            'primaryWork' => $primaryWork,
            'workCompleted' => $workCompleted, 'workDay' => $day,
            'imports' => $module === 'prazos' && $request->attributes->get('is_admin') ? DB::table('spreadsheet_imports')->where('office_id', $officeId)->whereNotNull('mapping')->select('id', 'filename', 'created_at')->orderByDesc('id')->get() : collect(),
            'office' => DB::table('offices')->find($officeId),
            'demo' => $request->attributes->get('demo'),
            'clients' => $clients, 'cases' => $cases,
            'documents' => $module === 'documentos' ? $access->query($request, 'documents')->select('id', 'name', 'legal_case_id', 'mime', 'size', 'created_at')->orderByDesc('created_at')->get() : collect(),
            'members' => in_array($module, ['equipe', 'prazos', 'configuracoes'], true) ? DB::table('members')->where('office_id', $officeId)->when(! in_array($module, ['equipe', 'configuracoes'], true), fn ($q) => $q->where('active', true))->when(! $request->attributes->get('is_admin'), fn ($q) => $q->where('id', $request->attributes->get('member_id')))->orderBy('name')->get() : collect(),
            'messages' => $module === 'mensagens' ? $access->query($request, 'messages')->orderBy('created_at')->get() : collect(),
        ]);
    }

    private function owned(Request $request, string $table): Builder
    {
        return app(WorkspacePermissions::class)->query($request, $table);
    }

    private function caseRule(Request $request): mixed
    {
        return Rule::exists('legal_cases', 'id')->where(fn ($q) => $q->where('office_id', $request->attributes->get('office_id'))->when(! $request->attributes->get('is_admin'), fn ($q) => $q->where('assigned_member_id', $request->attributes->get('member_id'))));
    }

    private function activity(Request $request, string $description, string $kind = 'task'): void
    {
        DB::table('activities')->insert([
            'office_id' => $request->attributes->get('office_id'), 'description' => $description,
            'actor' => $request->attributes->get('actor'), 'kind' => $kind, 'created_at' => now(),
        ]);
    }

    private function recordRules(Request $request, string $kind): array
    {
        $caseRule = $this->caseRule($request);

        return match ($kind) {
            'task' => ['title' => 'required|string|max:200', 'context' => 'nullable|string|max:2000',
                'due_at' => 'required|date', 'priority' => ['required', Rule::in(['Alta', 'Média', 'Baixa'])],
                'legal_case_id' => ['nullable', $caseRule]],
            'client' => ['name' => 'required|string|max:200', 'email' => 'nullable|email|max:200',
                'phone' => 'nullable|string|max:30', 'notes' => 'nullable|string|max:2000'],
            'case' => ['title' => 'required|string|max:200', 'number' => ['nullable', 'regex:/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/',
                Rule::unique('legal_cases', 'number')->where('office_id', $request->attributes->get('office_id'))],
                'court' => 'required|string|max:200', 'client_id' => ['required', Rule::exists('clients', 'id')->where('office_id', $request->attributes->get('office_id'))],
                'status' => ['required', Rule::in(['Em andamento', 'Aguardando audiência', 'Concluído'])]],
            'appointment' => ['title' => 'required|string|max:200', 'starts_at' => 'required|date',
                'location' => 'required|string|max:200', 'kind' => ['required', Rule::in(['Reunião', 'Audiência', 'Atendimento'])], 'legal_case_id' => ['nullable', $caseRule]],
            'deadline' => ['title' => 'required|string|max:200', 'due_at' => 'required|date', 'legal_case_id' => ['required', $caseRule]],
            default => abort(404),
        };
    }

    public function store(Request $request, string $kind): JsonResponse
    {
        abort_unless($request->attributes->get('is_admin'), 403);
        $data = $request->validate($this->recordRules($request, $kind));
        $data['office_id'] = $request->attributes->get('office_id');
        $record = DB::transaction(function () use ($request, $kind, $data): mixed {
            $record = match ($kind) {
                'task' => Task::create($data), 'client' => Client::create($data),
                'case' => LegalCase::create($data + ['responsible' => $request->attributes->get('actor')]),
                'appointment' => Appointment::create($data),
                'deadline' => DB::table('deadlines')->insertGetId($data + ['created_at' => now(), 'updated_at' => now()]),
            };
            $description = match ($kind) {
                'task' => 'Tarefa criada: '.$data['title'], 'client' => 'Cliente adicionado: '.$data['name'],
                'case' => 'Processo adicionado: '.$data['title'], 'appointment' => 'Compromisso agendado: '.$data['title'],
                'deadline' => 'Prazo registrado: '.$data['title'],
            };
            $this->activity($request, $description, $kind);

            return $record;
        });

        return response()->json(['message' => 'Registro salvo.', 'record' => $record], 201);
    }

    public function completeTask(Request $request, int $id, Dashboard $dashboard): JsonResponse
    {
        $data = $request->validate(['completed' => 'required|boolean']);
        abort_unless(app(WorkspacePermissions::class)->allows($request, 'tarefas.update'), 403);
        $row = $this->owned($request, 'tasks')->where('id', $id)->first();
        abort_unless($row, 404);
        $task = Task::findOrFail($row->id);
        DB::transaction(function () use ($request, $task, $data): void {
            $changed = ($task->completed_at !== null) !== $data['completed'];
            $task->update(['completed_at' => $data['completed'] ? ($task->completed_at ?? now()) : null]);
            if ($changed) {
                $this->activity($request, ($data['completed'] ? 'Tarefa concluída: ' : 'Tarefa reaberta: ').$task->title);
            }
        });
        $summary = $dashboard->data((int) $request->attributes->get('office_id'), $request);

        return response()->json([
            'completed' => $task->completed_at !== null,
            'pending' => $summary['pendingCount'], 'completedCount' => $summary['completedCount'],
            'next' => $summary['nextTask'], 'message' => $data['completed'] ? 'Tarefa concluída.' : 'Tarefa reaberta.',
        ]);
    }

    public function updateTask(Request $request, int $id): JsonResponse
    {
        abort_unless(app(WorkspacePermissions::class)->allows($request, 'tarefas.update'), 403);
        $row = $this->owned($request, 'tasks')->where('id', $id)->first();
        abort_unless($row, 404);
        $task = Task::findOrFail($row->id);
        $data = $request->validate(['title' => 'required|string|max:200', 'context' => 'nullable|string|max:2000',
            'due_at' => 'required|date', 'priority' => ['required', Rule::in(['Alta', 'Média', 'Baixa'])],
            'legal_case_id' => $request->attributes->get('is_admin') ? ['nullable', $this->caseRule($request)] : ['nullable', Rule::in([$task->legal_case_id])]]);
        if (! $request->attributes->get('is_admin')) {
            $data['legal_case_id'] = $task->legal_case_id;
        }
        DB::transaction(function () use ($task, $data, $request): void {
            $task->update($data);
            $this->activity($request, 'Tarefa atualizada: '.$task->title);
        });

        return response()->json(['message' => 'Tarefa atualizada.']);
    }

    public function completeDeadline(Request $request, int $id): JsonResponse
    {
        abort_unless($request->attributes->get('is_admin'), 403);
        $row = $this->owned($request, 'deadlines')->where('id', $id)->first();
        abort_unless($row, 404);
        $data = $request->validate(['completed' => 'required|boolean']);
        DB::transaction(function () use ($request, $id, $data, $row): void {
            $this->owned($request, 'deadlines')->where('id', $id)->update(['completed_at' => $data['completed'] ? now() : null, 'updated_at' => now()]);
            $this->activity($request, ($data['completed'] ? 'Prazo cumprido: ' : 'Prazo reaberto: ').$row->title, 'deadline');
        });

        return response()->json(['message' => 'Prazo atualizado.']);
    }

    public function detail(Request $request, string $kind, int $id): JsonResponse
    {
        if ($kind === 'deadline') {
            abort_unless($request->attributes->get('is_admin'), 403);
        }
        $table = match ($kind) {
            'case' => 'legal_cases', 'task' => 'tasks', 'client' => 'clients', 'appointment' => 'appointments', 'deadline' => 'deadlines', default => abort(404),
        };
        $record = $this->owned($request, $table)->where('id', $id)->first();
        abort_unless($record, 404);
        $related = [];
        if ($kind === 'case') {
            $related['client'] = $this->owned($request, 'clients')->where('id', $record->client_id)->value('name');
            $related['tasks'] = $this->owned($request, 'tasks')->where('legal_case_id', $id)->orderBy('due_at')->get();
            $related['deadlines'] = $this->owned($request, 'deadlines')->where('legal_case_id', $id)->orderBy('due_at')->get();
            $related['documents'] = $this->owned($request, 'documents')->where('legal_case_id', $id)->select('id', 'name')->get();
        }

        if ($kind === 'client') {
            $related['cases'] = $this->owned($request, 'legal_cases')->where('client_id', $id)->get();
        }

        return response()->json(['record' => $record, 'related' => $related, 'can_edit' => ($kind === 'task' && app(WorkspacePermissions::class)->allows($request, 'tarefas.update')) || ($request->attributes->get('is_admin') && in_array($kind, ['case', 'client', 'appointment'], true))]);
    }

    public function update(Request $request, string $kind, int $id): JsonResponse
    {
        abort_unless($request->attributes->get('is_admin'), 403);
        $table = match ($kind) {
            'client' => 'clients', 'case' => 'legal_cases', 'appointment' => 'appointments', default => abort(404)
        };
        $query = $this->owned($request, $table)->where('id', $id);
        abort_unless((clone $query)->exists(), 404);
        $rules = $this->recordRules($request, $kind);
        if ($kind === 'case') {
            $rules['number'][2]->ignore($id);
        }
        $data = $request->validate($rules);
        $query->update($data + ['updated_at' => now()]);
        $this->activity($request, 'Registro atualizado: '.($data['title'] ?? $data['name']), $kind);

        return response()->json(['message' => 'Alterações salvas.']);
    }

    private function messageClients(Request $request): Builder
    {
        return DB::table('clients')->where('office_id', $request->attributes->get('office_id'))
            ->when(! $request->attributes->get('is_admin'), fn ($q) => $q->whereIn('id', DB::table('legal_cases')->where('office_id', $request->attributes->get('office_id'))->where('assigned_member_id', $request->attributes->get('member_id'))->select('client_id')));
    }

    public function message(Request $request): JsonResponse
    {
        $access = app(WorkspacePermissions::class);
        abort_unless($access->allows($request, 'mensagens.view') && $access->allows($request, 'mensagens.send'), 403);
        $data = $request->validate(['client_id' => 'required|integer', 'body' => 'required|string|max:5000']);
        $body = trim($data['body']);
        abort_if($body === '', 422, 'Escreva uma mensagem.');
        abort_unless($this->messageClients($request)->where('id', $data['client_id'])->exists(), 404);
        $id = DB::table('messages')->insertGetId(['office_id' => $request->attributes->get('office_id'), 'client_id' => $data['client_id'], 'body' => $body, 'outgoing' => true, 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['message' => DB::table('messages')->find($id)], 201);
    }

    public function activities(Request $request): JsonResponse
    {
        abort_unless($request->attributes->get('is_admin'), 403);

        return response()->json(DB::table('activities')->where('office_id', $request->attributes->get('office_id'))->orderByDesc('created_at')->orderByDesc('id')->paginate(25));
    }

    public function upload(Request $request): JsonResponse
    {
        abort_unless(app(WorkspacePermissions::class)->allows($request, 'documentos.upload'), 403);
        if (! $request->attributes->get('is_admin')) {
            abort_unless($request->filled('legal_case_id'), 422);
        }
        $data = $request->validate(['file' => 'required|file|mimes:pdf,doc,docx,txt,jpg,jpeg,png|max:10240',
            'legal_case_id' => ['nullable', $this->caseRule($request)]]);
        $file = $request->file('file');
        $inDatabase = config('facilitajud.document_storage') === 'database';
        $path = $inDatabase ? 'database' : $file->store('offices/'.$request->attributes->get('office_id').'/documents', 'local');
        try {
            DB::transaction(function () use ($request, $file, $path, $data, $inDatabase): void {
                Document::create(['office_id' => $request->attributes->get('office_id'),
                    'legal_case_id' => $data['legal_case_id'] ?? null, 'name' => mb_substr($file->getClientOriginalName(), 0, 240),
                    'path' => $path, 'mime' => $file->getMimeType(), 'size' => $file->getSize(),
                    'contents' => $inDatabase ? base64_encode($file->getContent()) : null]);
                $this->activity($request, 'Documento adicionado: '.$file->getClientOriginalName(), 'document');
            });
        } catch (\Throwable $exception) {
            if (! $inDatabase) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return response()->json(['message' => 'Documento adicionado.'], 201);
    }

    public function download(Request $request, int $id): StreamedResponse
    {
        $row = $this->owned($request, 'documents')->where('id', $id)->first();
        abort_unless($row, 404);
        $document = Document::findOrFail($row->id);
        if ($document->path === 'database') {
            return response()->streamDownload(function () use ($document): void {
                echo base64_decode($document->contents, true);
            }, $document->name, ['Content-Type' => $document->mime, 'X-Content-Type-Options' => 'nosniff']);
        }
        abort_unless(Storage::disk('local')->exists($document->path), 404);

        return Storage::disk('local')->download($document->path, $document->name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function settings(Request $request): JsonResponse
    {
        abort_unless($request->attributes->get('is_admin'), 403);
        $data = $request->validate(['name' => 'required|string|max:160', 'display_name' => 'required|string|max:120', 'reminders' => 'required|boolean']);
        DB::transaction(function () use ($request, $data): void {
            DB::table('offices')->where('id', $request->attributes->get('office_id'))->update($data + ['updated_at' => now()]);
            $this->activity($request, 'Dados do escritório atualizados.', 'settings');
        });

        return response()->json(['message' => 'Alterações salvas.']);
    }
}
