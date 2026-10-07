<?php

namespace App\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspacePermissions
{
    public const OPTIONS = [
        'prazos.view' => 'Ver obrigações atribuídas', 'prazos.update' => 'Registrar andamentos das obrigações',
        'processos.view' => 'Ver processos atribuídos', 'tarefas.view' => 'Ver tarefas atribuídas', 'tarefas.update' => 'Editar e concluir tarefas atribuídas',
        'agenda.view' => 'Ver compromissos atribuídos', 'clientes.view' => 'Ver clientes dos processos atribuídos',
        'documentos.view' => 'Baixar documentos dos processos atribuídos', 'documentos.upload' => 'Adicionar documentos aos processos atribuídos',
        'equipe.view' => 'Ver minha categoria e responsabilidades', 'mensagens.view' => 'Ver conversas dos clientes atribuídos', 'mensagens.send' => 'Registrar mensagens nas conversas',
    ];

    public const DEFAULTS = ['prazos.view', 'prazos.update'];

    public function allows(Request $request, string $permission): bool
    {
        return $request->attributes->get('is_admin') || in_array($permission, $request->attributes->get('permissions', []), true);
    }

    public function module(Request $request, string $module): bool
    {
        return in_array($module, ['painel', 'configuracoes'], true) || $this->allows($request, $module.'.view');
    }

    public function query(Request $request, string $table): Builder
    {
        $q = DB::table($table)->where($table.'.office_id', $request->attributes->get('office_id'));
        if ($request->attributes->get('is_admin')) {
            return $q;
        }
        $module = match ($table) {
            'legal_cases' => 'processos', 'tasks' => 'tarefas', 'appointments' => 'agenda', 'clients' => 'clientes', 'documents' => 'documentos', 'messages' => 'mensagens', 'work_items' => 'prazos', default => null
        };
        if (! $module || ! $this->module($request, $module)) {
            return $q->whereRaw('1=0');
        }
        $member = $request->attributes->get('member_id');
        $cases = DB::table('legal_cases')->where('office_id', $request->attributes->get('office_id'))->where('assigned_member_id', $member);

        return match ($table) {
            'legal_cases', 'tasks', 'appointments', 'work_items' => $q->where($table.'.assigned_member_id', $member),
            'documents' => $q->whereIn('legal_case_id', (clone $cases)->select('id')),
            'clients' => $q->whereIn('id', (clone $cases)->select('client_id')),
            'messages' => $q->whereIn('client_id', (clone $cases)->select('client_id')),
            default => $q->whereRaw('1=0'),
        };
    }
}
