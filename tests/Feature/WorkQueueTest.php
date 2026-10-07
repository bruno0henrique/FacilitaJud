<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use ZipArchive;

class WorkQueueTest extends TestCase
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
        $this->member = DB::table('members')->where('office_id', $this->office)->value('id');
    }

    private function file(int $count = 1): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'excel-test-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Grade" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Target="worksheets/sheet1.xml"/></Relationships>');
        $date = now()->format('d/m/Y');
        $zip->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1" t="inlineStr"><is><t>Obrigação</t></is></c><c r="B1" t="inlineStr"><is><t>Data</t></is></c><c r="C1" t="inlineStr"><is><t>Processo</t></is></c></row><row r="2"><c r="A2" t="inlineStr"><is><t>Conferir custas</t></is></c><c r="B2" t="inlineStr"><is><t>'.$date.'</t></is></c><c r="C2" t="inlineStr"><is><t>0012345-67</t></is></c><c r="D2"><f>1+1</f><v>2</v></c></row></sheetData></worksheet>');
        if ($count > 1) {
            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $rows = '';
            for ($row = 3; $row <= $count + 1; $row++) {
                $rows .= '<row r="'.$row.'"><c r="A'.$row.'" t="inlineStr"><is><t>Conferir processo '.$row.'</t></is></c><c r="B'.$row.'" t="inlineStr"><is><t>'.$date.'</t></is></c></row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', str_replace('</sheetData>', $rows.'</sheetData>', $sheet));
        }
        $zip->addFromString('original-preservado.txt', 'Conteúdo original');
        $zip->close();
        $contents = file_get_contents($path);
        unlink($path);

        return UploadedFile::fake()->createWithContent('grade.xlsx', $contents);
    }

    private function importFile(): int
    {
        $id = $this->post('/api/v1/work/preview', ['file' => $this->file(), 'header_row' => 1], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('total', 1)->json('id');
        $this->postJson('/api/v1/work/import/'.$id, ['sheet' => 'Grade', 'header_row' => 1, 'mapping' => ['title' => 'A', 'due_at' => 'B', 'process_number' => 'C'], 'assigned_member_id' => $this->member])->assertOk()->assertJsonPath('count', 1);

        return $id;
    }

    public function test_import_update_history_and_export_preserve_original_workbook(): void
    {
        $id = $this->importFile();
        $item = DB::table('work_items')->first();
        $this->assertSame('0012345-67', $item->process_number);
        $this->patchJson('/api/v1/work/'.$item->id, ['version' => 1, 'status' => 'Concluído', 'note' => 'Custas conferidas; comprovante anexado.'])->assertOk();
        $this->getJson('/api/v1/work/'.$item->id)->assertJsonPath('history.0.note', 'Custas conferidas; comprovante anexado.');
        $this->get('/prazos')->assertOk()->assertSee('Tudo em dia');
        $this->patchJson('/api/v1/work/'.$item->id, ['version' => 1, 'status' => 'Pendente', 'note' => 'Alteração desatualizada'])->assertConflict();
        $download = $this->get('/prazos/planilha/'.$id)->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'export-test-');
        file_put_contents($path, $download->getContent());
        $zip = new ZipArchive;
        $zip->open($path);
        $this->assertSame('Conteúdo original', $zip->getFromName('original-preservado.txt'));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('<f>1+1</f>', $xml);
        $this->assertStringContainsString('Custas conferidas', $xml);
        $this->assertStringContainsString('Concluído', $xml);
        $zip->close();
        unlink($path);
        $this->postJson('/api/v1/work/import/'.$id, ['sheet' => 'Grade', 'header_row' => 1, 'mapping' => ['title' => 'A', 'due_at' => 'B'], 'assigned_member_id' => $this->member])->assertConflict();
    }

    public function test_staff_cannot_read_others_work_import_assign_or_export_original(): void
    {
        $id = $this->importFile();
        $item = DB::table('work_items')->first();
        $staff = DB::table('members')->insertGetId(['office_id' => $this->office, 'provider_id' => 'staff-user', 'name' => 'Funcionário', 'email' => 'staff@example.test', 'role' => 'Funcionário(a)', 'created_at' => now(), 'updated_at' => now()]);
        config(['facilitajud.demo' => false]);
        $this->withSession(['identity' => ['id' => 'staff-user', 'expires_at' => time() + 900]]);
        $this->getJson('/api/v1/work/'.$item->id)->assertNotFound();
        $this->patchJson('/api/v1/work/'.$item->id, ['version' => 1, 'status' => 'Concluído', 'note' => 'Não autorizado'])->assertNotFound();
        $this->get('/prazos')->assertDontSee('Conferir custas');
        $this->postJson('/api/v1/work/assign', ['ids' => [$item->id], 'member_id' => $staff])->assertForbidden();
        $this->get('/prazos/planilha/'.$id)->assertForbidden();
        $this->post('/api/v1/work/preview', ['file' => $this->file()], ['Accept' => 'application/json'])->assertForbidden();
        DB::table('work_items')->where('id', $item->id)->update(['assigned_member_id' => $staff]);
        $this->getJson('/api/v1/work/'.$item->id)->assertOk();
        $this->get('/painel')->assertOk()->assertSee('Abrir obrigação')->assertViewHas('primaryWork', fn ($work) => $work->id === $item->id);
        $this->patchJson('/api/v1/work/'.$item->id, ['version' => 1, 'status' => 'Em andamento', 'note' => 'Consultando o andamento.'])->assertOk();
    }

    public function test_two_hundred_obligations_can_be_imported_and_distributed_in_one_batch(): void
    {
        $id = $this->post('/api/v1/work/preview', ['file' => $this->file(200)], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('total', 200)->json('id');
        $payload = ['sheet' => 'Grade', 'header_row' => 1, 'mapping' => ['title' => 'A', 'due_at' => 'C'], 'assigned_member_id' => $this->member];
        $this->postJson('/api/v1/work/import/'.$id, $payload)->assertUnprocessable();
        $this->assertSame(0, DB::table('work_items')->count());
        $payload['mapping']['due_at'] = 'B';
        $this->postJson('/api/v1/work/import/'.$id, $payload)->assertOk()->assertJsonPath('count', 200);
        $second = DB::table('members')->where('office_id', $this->office)->where('id', '!=', $this->member)->value('id');
        $this->postJson('/api/v1/work/assign', ['ids' => DB::table('work_items')->pluck('id')->all(), 'member_id' => $second])->assertOk();
        $this->assertSame(200, DB::table('work_items')->where('assigned_member_id', $second)->count());
        $this->assertSame(200, DB::table('work_item_updates')->count());
        $this->get('/prazos')->assertOk()->assertViewHas('workTotal', 200)->assertViewHas('workItems', fn ($items) => $items->count() === 25);
    }
}
