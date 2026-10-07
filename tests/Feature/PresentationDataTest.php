<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Services\ExcelWorkbook;
use App\Services\PresentationData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PresentationDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_presentation_is_coherent_scoped_and_preserves_edits_on_rerun(): void
    {
        $office = Office::factory()->create();
        $other = Office::factory()->create();
        DB::table('members')->insert(['office_id' => $office->id, 'name' => 'Administrador', 'email' => 'admin@example.test', 'provider_id' => 'presentation-admin', 'account_type' => 'admin']);
        $original = DB::table('clients')->insertGetId(['office_id' => $office->id, 'name' => 'Cadastro existente']);
        $service = app(PresentationData::class);
        $this->assertTrue($service->populate($office->id)['created']);
        $this->assertSame(25, DB::table('clients')->where('office_id', $office->id)->count());
        $this->assertSame(24, DB::table('legal_cases')->where('office_id', $office->id)->count());
        $this->assertSame(48, DB::table('tasks')->where('office_id', $office->id)->count());
        $this->assertSame(120, DB::table('work_items')->where('office_id', $office->id)->count());
        $this->assertSame(24, DB::table('work_item_updates')->count());
        $this->assertDatabaseHas('clients', ['id' => $original, 'name' => 'Cadastro existente']);
        $this->assertSame(0, DB::table('clients')->where('office_id', $other->id)->count());
        $import = DB::table('spreadsheet_imports')->where('office_id', $office->id)->first();
        $this->assertSame(hash('sha256', base64_decode($import->contents)), $import->checksum);
        $read = app(ExcelWorkbook::class)->run(['action' => 'read', 'contents' => $import->contents, 'sheet' => 'Rotina', 'header_row' => 1]);
        $this->assertCount(120, $read['rows']);
        $first = DB::table('work_items')->where('office_id', $office->id)->first();
        $case = DB::table('legal_cases')->where('office_id', $office->id)->where('number', $first->process_number)->first();
        $this->assertSame($case->assigned_member_id, $first->assigned_member_id);
        $this->assertSame($first->title, $read['rows'][0]['values']['A']);
        DB::table('work_items')->where('id', $first->id)->update(['note' => 'Atualização feita pelo funcionário']);
        $this->assertFalse($service->populate($office->id)['created']);
        $this->assertSame(120, DB::table('work_items')->where('office_id', $office->id)->count());
        $this->assertDatabaseHas('work_items', ['id' => $first->id, 'note' => 'Atualização feita pelo funcionário']);
        config(['facilitajud.demo' => false]);
        $this->withSession(['identity' => ['id' => 'presentation-admin', 'expires_at' => time() + 600]])->get('/prazos')->assertOk()->assertSee('Rotina de acompanhamento.xlsx');
        $this->artisan('facilitajud:prepare-presentation', ['--email' => 'missing@example.test'])->assertFailed();
    }
}
