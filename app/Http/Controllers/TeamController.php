<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TeamController extends Controller
{
    private function admin(Request $request): void
    {
        abort_unless($request->attributes->get('is_admin'), 403);
    }

    private function rules(Request $request): array
    {
        return ['category_id' => ['nullable', 'integer', Rule::exists('team_categories', 'id')->where('office_id', $request->attributes->get('office_id'))],
            'responsibilities' => 'nullable|string|max:5000', 'permissions' => 'nullable|array', 'permissions.*' => ['string', Rule::in(array_keys(WorkspacePermissions::OPTIONS))]];
    }

    public function category(Request $request, ?int $id = null): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate(['name' => ['required', 'string', 'max:120', Rule::unique('team_categories')->where('office_id', $request->attributes->get('office_id'))->ignore($id)],
            'responsibilities' => 'nullable|string|max:5000', 'permissions' => 'present|array', 'permissions.*' => ['string', Rule::in(array_keys(WorkspacePermissions::OPTIONS))]]);
        $data['permissions'] = json_encode(array_values(array_unique($data['permissions'])));
        $data['updated_at'] = now();
        if ($id) {
            $q = DB::table('team_categories')->where('office_id', $request->attributes->get('office_id'))->where('id', $id);
            abort_unless((clone $q)->exists(), 404);
            $q->update($data);
        } else {
            $id = DB::table('team_categories')->insertGetId($data + ['office_id' => $request->attributes->get('office_id'), 'created_at' => now()]);
        }

        return response()->json(['id' => $id, 'message' => 'Categoria salva. Os acessos herdados já foram atualizados.']);
    }

    public function member(Request $request, int $id): JsonResponse
    {
        $this->admin($request);
        $q = DB::table('members')->where('office_id', $request->attributes->get('office_id'))->where('id', $id);
        $member = (clone $q)->first();
        abort_unless($member, 404);
        abort_if($member->account_type === 'admin', 403, 'O administrador mantém o acesso completo ao escritório.');
        $data = $request->validate($this->rules($request));
        $q->update(['category_id' => $data['category_id'] ?? null, 'responsibilities' => $data['responsibilities'] ?? null,
            'permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null, 'updated_at' => now()]);

        return response()->json(['message' => 'Acessos e responsabilidades atualizados.']);
    }

    public function invite(Request $request): JsonResponse
    {
        $this->admin($request);
        $data = $request->validate($this->rules($request) + ['name' => 'required|string|max:120', 'email' => 'required|email|max:200']);
        $token = Str::random(64);
        DB::table('team_invitations')->insert(['office_id' => $request->attributes->get('office_id'), 'name' => $data['name'], 'email' => $data['email'],
            'category_id' => $data['category_id'] ?? null, 'responsibilities' => $data['responsibilities'] ?? null, 'permissions' => isset($data['permissions']) ? json_encode($data['permissions']) : null,
            'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);

        return response()->json(['url' => route('login', ['convite' => $token]), 'message' => 'Convite de associado criado. Compartilhe o link com o destinatário.']);
    }

    public function assign(Request $request, string $kind, int $id): JsonResponse
    {
        $this->admin($request);
        $table = match ($kind) {
            'case' => abort(422, 'A atribuição de processos será feita por planilha. O modelo está em definição.'),'task' => 'tasks','appointment' => 'appointments',default => abort(404)
        };
        $data = $request->validate(['assigned_member_id' => ['nullable', 'integer', Rule::exists('members', 'id')->where('office_id', $request->attributes->get('office_id'))]]);
        $q = DB::table($table)->where('office_id', $request->attributes->get('office_id'))->where('id', $id);
        abort_unless((clone $q)->exists(), 404);
        $values = ['assigned_member_id' => $data['assigned_member_id'] ?? null, 'updated_at' => now()];
        $q->update($values);

        return response()->json(['message' => 'Responsável atualizado.']);
    }
}
