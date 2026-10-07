<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Dashboard
{
    public function data(int $officeId, ?Request $request = null, string $module = 'painel'): array
    {
        $loadTasks = in_array($module, ['painel', 'tarefas', 'equipe'], true);
        $tasks = $loadTasks ? Task::where('office_id', $officeId)->when($request, fn ($q) => $q->whereIn('id', app(WorkspacePermissions::class)->query($request, 'tasks')->select('id')))->orderBy('due_at')->get() : collect();
        $taskCounts = $loadTasks ? null : app(WorkspacePermissions::class)->query($request, 'tasks')
            ->selectRaw('COUNT(*) as total, COUNT(completed_at) as completed')->first();
        $pending = $tasks->whereNull('completed_at');
        $next = $pending->sortBy(fn (Task $task): string => $task->due_at->format('Y-m-d').match ($task->priority) {
            'Alta' => '0', 'Média' => '1', default => '2',
        }.$task->due_at->format('H:i')
        )->first();
        $deadlines = in_array($module, ['painel', 'prazos'], true) ? DB::table('deadlines')->join('legal_cases', 'legal_cases.id', '=', 'deadlines.legal_case_id')
            ->where('deadlines.office_id', $officeId)->when($request && ! $request->attributes->get('is_admin'), fn ($q) => $q->whereRaw('1=0'))->whereNull('deadlines.completed_at')
            ->select('deadlines.*', 'legal_cases.title as case_title')->orderBy('due_at')->get() : collect();
        $appointments = in_array($module, ['painel', 'agenda', 'equipe'], true) ? DB::table('appointments')->where('office_id', $officeId)
            ->when($request, fn ($q) => $q->whereIn('id', app(WorkspacePermissions::class)->query($request, 'appointments')->select('id')))
            ->where('starts_at', '>=', now()->startOfDay())->orderBy('starts_at')->get() : collect();

        return [
            'tasks' => $tasks,
            'pendingCount' => $loadTasks ? $pending->count() : (int) $taskCounts->total - (int) $taskCounts->completed,
            'completedCount' => $loadTasks ? $tasks->whereNotNull('completed_at')->count() : (int) $taskCounts->completed,
            'nextTask' => $next,
            'deadlines' => $deadlines,
            'dueTodayCount' => $deadlines->filter(fn ($row): bool => Carbon::parse($row->due_at)->isToday())->count(),
            'overdueCount' => $deadlines->filter(fn ($row): bool => Carbon::parse($row->due_at)->isBefore(now()->startOfDay()))->count(),
            'weekDeadlinesCount' => $deadlines->filter(fn ($row): bool => Carbon::parse($row->due_at)->between(now()->startOfDay(), now()->addDays(7)->endOfDay()))->count(),
            'appointments' => $appointments,
            'todayAppointmentsCount' => $appointments->filter(fn ($row): bool => Carbon::parse($row->starts_at)->isToday())->count(),
            'todayAppointment' => $appointments->first(fn ($row): bool => Carbon::parse($row->starts_at)->isToday()),
            'weekAppointmentsCount' => $appointments->filter(fn ($row): bool => Carbon::parse($row->starts_at)->isBefore(now()->addDays(7)->endOfDay()))->count(),
            'activities' => $module === 'painel' ? DB::table('activities')->where('office_id', $officeId)->when($request && ! $request->attributes->get('is_admin'), fn ($q) => $q->whereRaw('1=0'))->orderByDesc('created_at')->limit(4)->get() : collect(),
        ];
    }
}
