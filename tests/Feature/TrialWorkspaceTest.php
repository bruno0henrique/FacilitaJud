<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkspaceController;
use App\Models\Office;
use App\Services\NeonAuth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrialWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => false, 'facilitajud.trial_enabled' => true]);
    }

    public function test_trial_opens_without_auth_and_is_filled_and_editable(): void
    {
        $this->get('/entrar')->assertOk()->assertSee('Testar o programa');
        $this->get('/painel')->assertRedirect('/entrar');
        $this->post('/testar')->assertRedirect('/painel')->assertSessionMissing('identity');
        $office = DB::table('offices')->first();
        $this->assertTrue((bool) $office->is_demo);
        $this->assertSame(24, DB::table('legal_cases')->count());
        $this->assertSame(48, DB::table('tasks')->count());
        $this->assertSame(120, DB::table('work_items')->count());
        $this->assertSame(24, DB::table('documents')->count());
        foreach (array_keys(WorkspaceController::MODULES) as $module) {
            $this->get('/'.$module)->assertOk()->assertSee('FacilitaJud');
        }
        $task = DB::table('tasks')->whereNull('completed_at')->first();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => true])->assertOk();
        $this->assertNotNull(DB::table('tasks')->find($task->id)->completed_at);
        $this->post('/testar')->assertRedirect('/painel');
        $this->assertSame(1, DB::table('offices')->count());
        $this->assertSame(48, DB::table('tasks')->count());
    }

    public function test_trial_cannot_read_or_change_other_office_records(): void
    {
        $other = Office::factory()->create();
        $task = DB::table('tasks')->insertGetId(['office_id' => $other->id, 'title' => 'Tarefa privada', 'context' => 'Privado', 'due_at' => now(), 'priority' => 'Alta']);
        $this->postJson('/testar')->assertOk()->assertJsonPath('redirect', url('/painel'));
        $this->get('/tarefas')->assertDontSee('Tarefa privada');
        $this->getJson('/api/v1/records/task/'.$task)->assertNotFound();
        $this->patchJson('/api/v1/tasks/'.$task.'/completion', ['completed' => true])->assertNotFound();
        $this->assertNull(DB::table('tasks')->find($task)->completed_at);
        $this->withSession(['trial_workspace' => ['office_id' => $other->id, 'expires_at' => time() + 600]])->get('/painel')->assertRedirect('/entrar');
    }

    public function test_expired_sessions_and_logout_revoke_trial_access(): void
    {
        $this->post('/testar')->assertRedirect('/painel');
        $office = DB::table('offices')->first();
        $this->withSession(['trial_workspace' => ['office_id' => $office->id, 'expires_at' => time() - 1]])->getJson('/api/v1/records/task/1')->assertUnauthorized()->assertSessionMissing('trial_workspace');
        $this->post('/testar')->assertRedirect('/painel');
        $this->post('/sair')->assertRedirect('/entrar')->assertSessionMissing('trial_workspace');
        $this->get('/painel')->assertRedirect('/entrar');
    }

    public function test_real_login_leaves_trial_and_keeps_private_office_separate(): void
    {
        $office = Office::factory()->create(['name' => 'Escritório privado']);
        DB::table('members')->insert(['office_id' => $office->id, 'name' => 'Ana', 'email' => 'ana@example.test', 'provider_id' => 'real-user', 'account_type' => 'admin']);
        $this->post('/testar')->assertRedirect('/painel');
        $this->mock(NeonAuth::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn((object) ['sub' => 'real-user', 'email' => 'ana@example.test', 'name' => 'Ana', 'exp' => time() + 900]);
        });
        $this->withHeader('Authorization', 'Bearer verified')->postJson('/auth/neon/session')->assertOk()->assertSessionMissing('trial_workspace');
        $this->get('/painel')->assertOk()->assertSee('Escritório privado')->assertDontSee('Luiza Monteiro');
        $this->assertSame(0, DB::table('legal_cases')->where('office_id', $office->id)->count());
    }

    public function test_real_account_cannot_accept_invitation_from_trial(): void
    {
        $this->post('/testar')->assertRedirect('/painel');
        $this->postJson('/api/v1/team/invite', ['name' => 'Pessoa', 'email' => 'person@example.test'])->assertOk();
        DB::table('team_invitations')->update(['token_hash' => hash('sha256', 'trial-invite')]);
        $this->mock(NeonAuth::class, function ($mock): void {
            $mock->shouldReceive('verify')->once()->andReturn((object) ['sub' => 'new-user', 'email' => 'person@example.test', 'exp' => time() + 900]);
        });
        $this->withHeader('Authorization', 'Bearer verified')->postJson('/auth/neon/session', ['invitation' => 'trial-invite'])->assertForbidden();
        $this->assertFalse(DB::table('members')->where('provider_id', 'new-user')->exists());
    }

    public function test_trial_can_be_disabled(): void
    {
        config(['facilitajud.trial_enabled' => false]);
        $this->get('/entrar')->assertDontSee('Testar o programa');
        $this->post('/testar')->assertNotFound();
        $this->assertSame(0, DB::table('offices')->count());
    }
}
