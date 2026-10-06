@props(['tasks', 'completedCount', 'pendingCount', 'home' => false])
<section class="surface task-surface" aria-label="Lista de tarefas">
    <div class="section-head">
        <div><h2>{{ $home ? 'Tarefas do dia e próximos passos' : 'Suas tarefas' }}</h2><p><span data-pending-count>{{ $pendingCount }}</span> pendentes · <span data-completed-count>{{ $completedCount }}</span> concluídas</p></div>
        <select aria-label="Ordenar tarefas" id="task-sort"><option value="date">Ordenar por prazo</option><option value="priority">Por prioridade</option></select>
    </div>
    <div class="task-list" id="task-list">
        @foreach($tasks as $task)
            <div class="task-row {{ $task->completed_at ? 'is-complete' : '' }}" data-task-id="{{ $task->id }}" data-due="{{ $task->due_at->toIso8601String() }}" data-priority="{{ $task->priority }}" data-search="{{ mb_strtolower($task->title.' '.$task->context) }}" @if($home && $task->completed_at) hidden @endif>
                <input type="checkbox" class="task-checkbox" aria-label="Concluir {{ $task->title }}" @checked($task->completed_at)>
                <button class="task-content" data-detail="task" data-id="{{ $task->id }}"><span class="task-title">{{ $task->title }}</span><small>{{ $task->due_at->isToday() ? 'Prazo vence hoje às '.$task->due_at->format('H:i') : 'Prazo: '.$task->due_at->translatedFormat('d M').' · '.$task->due_at->format('H:i') }}<span class="task-context"> · {{ $task->context }}</span></small></button>
                <span class="badge {{ $task->priority === 'Alta' ? 'pink' : ($task->priority === 'Média' ? 'lavender' : 'green') }} task-status">{{ $task->completed_at ? 'Concluída' : $task->priority }}</span>
                <button class="icon-button row-more" aria-label="Editar {{ $task->title }}" data-edit-task="{{ $task->id }}"><x-icon name="ellipsis" /></button>
            </div>
        @endforeach
    </div>
    <div class="empty-state" id="task-empty" @if($tasks->count()) hidden @endif><x-icon name="list-checks"/><h3>Nenhuma tarefa por aqui.</h3><p>Adicione o próximo passo da sua rotina.</p><button class="button primary" data-create="task">Criar tarefa</button></div>
    <div class="task-progress"><div><span><x-icon name="leaf"/> Um passo de cada vez.</span><span><span data-completed-count>{{ $completedCount }}</span> de {{ $tasks->count() }} concluídas</span></div><progress id="task-progress" value="{{ $completedCount }}" max="{{ max(1, $tasks->count()) }}" aria-label="Progresso das tarefas"></progress></div>
    @if($home)<a class="section-foot" href="{{ route('workspace', ['module' => 'tarefas']) }}">Ver todas as tarefas <x-icon name="arrow-right"/></a>@endif
</section>
