<?php

namespace Tests\Feature;

use App\Services\PresentationData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ModuleInteractionTest extends TestCase
{
    use RefreshDatabase;

    private int $office;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => true]);
        $this->seed();
        $this->office = DB::table('offices')->where('is_demo', true)->value('id');
        app(PresentationData::class)->populate($this->office);
    }

    public function test_messages_are_stored_scoped_and_keep_text_safe(): void
    {
        $client = DB::table('clients')->where('office_id', $this->office)->first();
        $this->postJson('/api/v1/messages', ['client_id' => $client->id, 'body' => '<script>alert(1)</script>'])->assertCreated();
        $this->get('/mensagens')->assertOk()->assertSee('Buscar nome')->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $other = DB::table('offices')->insertGetId(['name' => 'Outro', 'display_name' => 'Outro']);
        $private = DB::table('clients')->insertGetId(['office_id' => $other, 'name' => 'Privado']);
        $this->postJson('/api/v1/messages', ['client_id' => $private, 'body' => 'Teste'])->assertNotFound();
        $this->postJson('/api/v1/messages', ['client_id' => $client->id, 'body' => '   '])->assertUnprocessable();
    }

    public function test_clients_and_work_are_paginated_and_search_reaches_all_pages(): void
    {
        $response = $this->get('/clientes')->assertOk()->assertSee('Próxima')->assertDontSee('pagination.next');
        $this->assertSame(15, $response->viewData('clients')->count());
        $name = DB::table('clients')->where('office_id', $this->office)->orderByDesc('name')->value('name');
        $search = $this->get('/clientes?q='.urlencode($name))->assertOk()->assertSee($name);
        $this->assertSame(1, $search->viewData('clients')->total());
        $work = $this->get('/prazos')->assertOk();
        $this->assertSame(25, $work->viewData('workItems')->perPage());
    }

    public function test_calendar_includes_past_dates_and_month_navigation(): void
    {
        DB::table('appointments')->insert(['office_id' => $this->office, 'title' => 'Compromisso de março', 'starts_at' => '2026-03-05 10:00:00', 'location' => 'Escritório', 'kind' => 'Reunião']);
        $this->get('/agenda?month=2026-03')->assertOk()->assertSee('Compromisso de março')->assertSee('Calendário mensal')->assertSee('Google Calendar');
        $this->get('/agenda?month=2026-04')->assertOk()->assertDontSee('Compromisso de março');
        $this->get('/agenda?month=invalid')->assertSessionHasErrors('month');
    }

    public function test_editable_records_and_history_stay_in_the_office(): void
    {
        $client = DB::table('clients')->where('office_id', $this->office)->first();
        $this->patchJson('/api/v1/records/client/'.$client->id, ['name' => 'Nome atualizado', 'notes' => 'Conferido'])->assertOk();
        $case = DB::table('legal_cases')->where('office_id', $this->office)->first();
        $this->patchJson('/api/v1/records/case/'.$case->id, ['title' => 'Processo revisado', 'number' => $case->number, 'court' => $case->court, 'client_id' => $case->client_id, 'status' => 'Concluído'])->assertOk();
        $appointment = DB::table('appointments')->where('office_id', $this->office)->first();
        $this->patchJson('/api/v1/records/appointment/'.$appointment->id, ['title' => 'Audiência revisada', 'starts_at' => now()->addDay()->toDateTimeString(), 'kind' => 'Audiência', 'location' => 'Sala 3'])->assertOk();
        $this->getJson('/api/v1/activities')->assertOk()->assertJsonPath('data.0.description', 'Registro atualizado: Audiência revisada');
        $this->get('/painel')->assertOk()->assertSee('Ver histórico')->assertSee('neo-launcher');
    }

    public function test_removed_associate_cannot_access_and_history_is_preserved(): void
    {
        $member = DB::table('members')->where('office_id', $this->office)->where('account_type', 'associate')->first();
        DB::table('members')->where('id', $member->id)->update(['provider_id' => 'associate-removed']);
        $this->patchJson('/api/v1/team/members/'.$member->id, ['permissions' => ['mensagens.view', 'mensagens.send']])->assertOk();
        $member = DB::table('members')->find($member->id);
        $this->patchJson('/api/v1/team/members/'.$member->id, ['active' => false])->assertOk();
        $this->assertDatabaseHas('members', ['id' => $member->id, 'category_id' => $member->category_id, 'permissions' => $member->permissions, 'responsibilities' => $member->responsibilities]);
        $this->assertDatabaseHas('members', ['id' => $member->id, 'active' => false]);
        $this->get('/equipe')->assertOk()->assertSee('Removido')->assertSee('Acessos atuais');
        $this->withSession(['identity' => ['id' => 'associate-removed', 'expires_at' => time() + 900]])->get('/painel')->assertForbidden();
    }

    public function test_associate_message_permission_and_client_assignment_are_enforced(): void
    {
        $member = DB::table('members')->where('office_id', $this->office)->where('account_type', 'associate')->first();
        DB::table('members')->where('id', $member->id)->update(['provider_id' => 'messaging-associate', 'permissions' => json_encode(['mensagens.view'])]);
        $case = DB::table('legal_cases')->where('assigned_member_id', $member->id)->first();
        $this->withSession(['identity' => ['id' => 'messaging-associate', 'expires_at' => time() + 900]]);
        $this->get('/mensagens')->assertOk();
        $this->postJson('/api/v1/messages', ['client_id' => $case->client_id, 'body' => 'Teste'])->assertForbidden();
        DB::table('members')->where('id', $member->id)->update(['permissions' => json_encode(['mensagens.view', 'mensagens.send'])]);
        $this->postJson('/api/v1/messages', ['client_id' => $case->client_id, 'body' => 'Conferência feita'])->assertCreated();
        $other = DB::table('legal_cases')->where('office_id', $this->office)->where('assigned_member_id', '!=', $member->id)->first();
        $this->postJson('/api/v1/messages', ['client_id' => $other->client_id, 'body' => 'Teste'])->assertNotFound();
        $this->getJson('/api/v1/activities')->assertForbidden();
    }
}
