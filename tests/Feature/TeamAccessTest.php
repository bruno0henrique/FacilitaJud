<?php

namespace Tests\Feature;

use App\Services\WorkspacePermissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TeamAccessTest extends TestCase
{
    use RefreshDatabase;

    private int $office;

    private int $member;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => true]);
        $this->seed();
        $this->office = DB::table('offices')->where('is_demo', true)->value('id');
        $this->member = DB::table('members')->insertGetId(['office_id' => $this->office, 'provider_id' => 'associate-test', 'name' => 'Associado de teste', 'email' => 'associated@example.test', 'role' => 'Administradora inventada', 'account_type' => 'associate', 'created_at' => now(), 'updated_at' => now()]);
    }

    private function associate(array $permissions): void
    {
        DB::table('members')->where('id', $this->member)->update(['permissions' => json_encode($permissions)]);
        config(['facilitajud.demo' => true]);
        $this->withSession(['identity' => ['id' => 'associate-test', 'expires_at' => time() + 3600]]);
    }

    public function test_admin_categories_and_invites_are_validated_and_inherit_access(): void
    {
        $id = $this->postJson('/api/v1/team/categories', ['name' => 'Assistente', 'responsibilities' => 'Conferir custas', 'permissions' => ['prazos.view', 'prazos.update']])->assertOk()->json('id');
        $this->postJson('/api/v1/team/categories', ['name' => 'Escalar', 'permissions' => ['admin']])->assertUnprocessable();
        $this->postJson('/api/v1/team/invite', ['name' => 'Nova pessoa', 'email' => 'new@example.test', 'category_id' => $id])->assertOk();
        $this->assertDatabaseHas('team_invitations', ['email' => 'new@example.test', 'category_id' => $id, 'permissions' => null]);
        $this->patchJson('/api/v1/team/members/'.$this->member, ['category_id' => $id, 'permissions' => null, 'responsibilities' => null])->assertOk();
        $this->patchJson('/api/v1/team/categories/'.$id, ['name' => 'Assistente', 'responsibilities' => 'Conferir custas', 'permissions' => []])->assertOk();
        config(['facilitajud.demo' => true]);
        $this->withSession(['identity' => ['id' => 'associate-test', 'expires_at' => time() + 3600]]);
        $this->get('/prazos')->assertForbidden();
        $this->get('/painel')->assertOk();
    }

    public function test_associate_cannot_escalate_or_change_office_or_team(): void
    {
        $this->associate(array_keys(WorkspacePermissions::OPTIONS));
        $this->postJson('/api/v1/team/invite', ['name' => 'Outra', 'email' => 'a@example.test'])->assertForbidden();
        $this->postJson('/api/v1/team/categories', ['name' => 'ADM', 'permissions' => []])->assertForbidden();
        $this->patchJson('/api/v1/team/members/'.$this->member, ['account_type' => 'admin'])->assertForbidden();
        $this->patchJson('/api/v1/settings', ['name' => 'Invadido'])->assertForbidden();
        $this->postJson('/api/v1/records/task', [])->assertForbidden();
        $this->postJson('/api/v1/work/assign', [])->assertForbidden();
    }

    public function test_only_assigned_records_are_visible_in_html_and_detail_and_task_summary(): void
    {
        $case = DB::table('legal_cases')->where('office_id', $this->office)->first();
        $other = DB::table('legal_cases')->where('office_id', $this->office)->where('id', '!=', $case->id)->first();
        DB::table('legal_cases')->where('id', $case->id)->update(['assigned_member_id' => $this->member]);
        $task = DB::table('tasks')->where('office_id', $this->office)->first();
        DB::table('tasks')->where('id', $task->id)->update(['assigned_member_id' => $this->member]);
        $this->associate(['processos.view', 'tarefas.view', 'tarefas.update']);
        $this->get('/processos')->assertOk()->assertSee($case->title)->assertDontSee($other->title);
        $this->getJson('/api/v1/records/case/'.$other->id)->assertNotFound();
        $this->getJson('/api/v1/records/case/'.$case->id)->assertOk();
        $otherTask = DB::table('tasks')->where('id', '!=', $task->id)->first();
        $this->get('/painel')->assertOk()->assertDontSee($otherTask->title);
        $this->patchJson('/api/v1/tasks/'.$otherTask->id.'/completion', ['completed' => true])->assertNotFound();
        $this->patchJson('/api/v1/tasks/'.$task->id, ['title' => 'Minha conferência', 'context' => 'Conferir', 'due_at' => now()->toIso8601String(), 'priority' => 'Alta', 'legal_case_id' => null])->assertOk();
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'legal_case_id' => $task->legal_case_id]);
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => true])->assertOk()->assertJsonPath('pending', 0)->assertJsonPath('completedCount', 1);
        DB::table('members')->where('id', $this->member)->update(['permissions' => '[]']);
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => false])->assertForbidden();
        $this->get('/tarefas')->assertForbidden();
        $this->getJson('/api/v1/records/task/'.$task->id)->assertNotFound();
    }

    public function test_category_and_assignments_cannot_cross_offices(): void
    {
        $foreign = DB::table('offices')->insertGetId(['name' => 'Outro', 'display_name' => 'Outro', 'created_at' => now(), 'updated_at' => now()]);
        $category = DB::table('team_categories')->insertGetId(['office_id' => $foreign, 'name' => 'Outro', 'permissions' => '[]', 'created_at' => now(), 'updated_at' => now()]);
        $this->patchJson('/api/v1/team/members/'.$this->member, ['category_id' => $category])->assertUnprocessable();
        $this->patchJson('/api/v1/team/categories/'.$category, ['name' => 'Outra', 'permissions' => []])->assertNotFound();
        $id = DB::table('legal_cases')->where('office_id', $this->office)->value('id');
        $this->patchJson('/api/v1/team/assign/case/'.$id, ['assigned_member_id' => $this->member])->assertOk();
        $this->assertDatabaseHas('legal_cases', ['id' => $id, 'assigned_member_id' => $this->member, 'responsible' => 'Associado de teste']);
    }

    public function test_documents_and_hidden_templates_do_not_leak_other_associates_data(): void
    {
        $case = DB::table('legal_cases')->where('office_id', $this->office)->first();
        DB::table('legal_cases')->where('id', $case->id)->update(['assigned_member_id' => $this->member]);
        $other = DB::table('legal_cases')->where('id', '!=', $case->id)->first();
        DB::table('legal_cases')->where('id', $other->id)->update(['title' => 'ASSUNTO PRIVADO DE OUTRO ASSOCIADO']);
        $other->title = 'ASSUNTO PRIVADO DE OUTRO ASSOCIADO';
        $document = DB::table('documents')->insertGetId(['office_id' => $this->office, 'legal_case_id' => $other->id, 'name' => 'SEGREDO OUTRO ASSOCIADO', 'path' => 'database', 'contents' => base64_encode('private'), 'mime' => 'text/plain', 'size' => 7, 'created_at' => now(), 'updated_at' => now()]);
        $this->associate(['documentos.view', 'documentos.upload', 'clientes.view', 'processos.view']);
        $this->get('/documentos')->assertOk()->assertDontSee('SEGREDO OUTRO ASSOCIADO')->assertDontSee($other->title);
        $this->get('/documentos/'.$document.'/baixar')->assertNotFound();
        config(['facilitajud.document_storage' => 'database']);
        $file = UploadedFile::fake()->createWithContent('conferencia.txt', 'Conferido');
        $this->post('/api/v1/documents', ['legal_case_id' => $other->id, 'file' => $file], ['Accept' => 'application/json'])->assertUnprocessable();
        DB::table('members')->where('id', $this->member)->update(['permissions' => json_encode(['documentos.view', 'documentos.upload'])]);
        $this->post('/api/v1/documents', ['legal_case_id' => $case->id, 'file' => $file], ['Accept' => 'application/json'])->assertCreated();
    }
}
