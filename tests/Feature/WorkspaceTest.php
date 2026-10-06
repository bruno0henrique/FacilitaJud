<?php

namespace Tests\Feature;

use App\Http\Controllers\WorkspaceController;
use App\Models\Client;
use App\Models\Document;
use App\Models\LegalCase;
use App\Models\Office;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['facilitajud.demo' => true]);
        $this->seed();
    }

    public function test_all_modules_render_with_the_same_workspace(): void
    {
        foreach (array_keys(WorkspaceController::MODULES) as $module) {
            $this->get('/'.$module)->assertOk()->assertSee('FacilitaJud')->assertSee('Escritório Helena Souza');
        }
    }

    public function test_completion_is_persistent_reversible_and_does_not_close_a_legal_deadline(): void
    {
        $task = Task::whereNull('completed_at')->firstOrFail();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => true])
            ->assertOk()->assertJsonPath('pending', 4)->assertJsonPath('completedCount', 6);
        $this->assertNotNull($task->fresh()->completed_at);
        $count = DB::table('activities')->count();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => true])->assertOk();
        $this->assertSame($count, DB::table('activities')->count());
        $this->assertSame(0, DB::table('deadlines')->whereNotNull('completed_at')->count());
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => false])->assertOk()->assertJsonPath('pending', 5);
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_crud_persists_related_records_and_validates_inputs(): void
    {
        $client = $this->postJson('/api/v1/records/client', ['name' => 'Cliente de teste', 'email' => 'teste@example.test'])
            ->assertCreated()->json('record.id');
        $case = $this->postJson('/api/v1/records/case', ['title' => 'Consultoria contratual', 'client_id' => $client,
            'court' => 'Consultivo', 'status' => 'Em andamento'])->assertCreated()->json('record.id');
        $task = $this->postJson('/api/v1/records/task', ['title' => 'Revisar contrato', 'due_at' => '2026-10-07T17:30',
            'priority' => 'Alta', 'legal_case_id' => $case])->assertCreated()->json('record.id');
        $this->patchJson('/api/v1/tasks/'.$task, ['title' => 'Revisar e enviar contrato', 'due_at' => '2026-10-08T18:30',
            'priority' => 'Média', 'legal_case_id' => $case])->assertOk();
        $this->getJson('/api/v1/records/case/'.$case)->assertOk()->assertJsonPath('related.tasks.0.title', 'Revisar e enviar contrato');
        $this->postJson('/api/v1/records/appointment', ['title' => 'Atendimento', 'starts_at' => '2026-10-07T14:30',
            'location' => 'Sala 1', 'kind' => 'Atendimento', 'legal_case_id' => $case])->assertCreated();
        $this->postJson('/api/v1/records/deadline', ['title' => 'Conferir documentos', 'due_at' => '2026-10-07T17:00',
            'legal_case_id' => $case])->assertCreated();
        $this->postJson('/api/v1/records/task', ['title' => '', 'due_at' => 'inválido', 'priority' => 'Urgentíssima'])
            ->assertUnprocessable()->assertJsonValidationErrors(['title', 'due_at', 'priority']);
    }

    public function test_an_office_cannot_read_or_modify_another_offices_records(): void
    {
        $office = Office::factory()->create();
        $client = Client::factory()->create(['office_id' => $office->id]);
        $case = LegalCase::factory()->create(['office_id' => $office->id, 'client_id' => $client->id]);
        $task = Task::factory()->create(['office_id' => $office->id]);
        $document = Document::factory()->create(['office_id' => $office->id]);
        $this->getJson('/api/v1/records/task/'.$task->id)->assertNotFound();
        $this->patchJson('/api/v1/tasks/'.$task->id.'/completion', ['completed' => true])->assertNotFound();
        $this->get('/documentos/'.$document->id.'/baixar')->assertNotFound();
        $this->postJson('/api/v1/records/task', ['title' => 'Não autorizado', 'priority' => 'Alta', 'due_at' => now()->toIso8601String(),
            'legal_case_id' => $case->id])->assertUnprocessable()->assertJsonValidationErrors('legal_case_id');
        $this->get('/clientes')->assertDontSee($client->name);
    }

    public function test_documents_survive_without_a_persistent_filesystem(): void
    {
        config(['facilitajud.document_storage' => 'database']);
        $content = 'Anotações para a reunião jurídica.';
        $this->post('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent('Reunião.txt', $content)],
            ['Accept' => 'application/json'])->assertCreated();
        $document = Document::firstOrFail();
        $this->assertSame('database', $document->path);
        $this->assertSame($content, base64_decode($document->contents));
        $response = $this->get('/documentos/'.$document->id.'/baixar')->assertOk()->assertDownload('Reuniao.txt')->assertStreamedContent($content);
        $this->assertStringContainsString("filename*=utf-8''Reuni%C3%A3o.txt", $response->headers->get('Content-Disposition'));
        $this->post('/api/v1/documents', ['file' => UploadedFile::fake()->createWithContent('script.php', '<?php echo 1;')],
            ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_demo_access_is_never_enabled_in_production(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');
        config(['facilitajud.demo' => true, 'facilitajud.demo_docker_loopback' => true]);
        $this->get('/')->assertRedirect('/entrar');
        $this->getJson('/api/v1/records/task/1')->assertUnauthorized();
    }

    public function test_expired_sessions_are_rejected(): void
    {
        config(['facilitajud.demo' => false]);
        $this->withSession(['identity' => ['id' => 'expired', 'expires_at' => time() - 1]])
            ->getJson('/api/v1/records/task/1')->assertUnauthorized();
    }
}
