<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspacePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_does_not_load_records_from_unrelated_modules(): void
    {
        config(['facilitajud.demo' => true]);
        $this->seed();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $this->get('/equipe')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        fwrite(STDOUT, "\nEquipe: ".count($queries)." consultas\n");
        foreach ($queries as $query) {
            foreach (['work_items', 'spreadsheet_imports', 'legal_cases', 'clients', 'documents', 'messages', 'activities', 'deadlines'] as $table) {
                $this->assertStringNotContainsString('"'.$table.'"', $query['query']);
            }
        }
    }

    public function test_demo_does_not_schedule_neon_session_refresh(): void
    {
        config(['facilitajud.demo' => true]);
        $this->seed();
        $this->get('/painel')->assertOk()->assertSee('name="neon-session-active" content="0"', false);
    }
}
