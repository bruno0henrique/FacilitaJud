<?php

namespace Database\Seeders;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\LegalCase;
use App\Models\Task;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing') || ! config('facilitajud.demo')) {
            return;
        }
        if (DB::table('offices')->where('is_demo', true)->exists()) {
            return;
        }
        DB::transaction(function (): void {
            $office = DB::table('offices')->insertGetId(['name' => 'Escritório Helena Souza', 'display_name' => 'Dra. Helena', 'is_demo' => true, 'created_at' => now(), 'updated_at' => now()]);
            foreach ([['Helena Souza', 'Administradora'], ['Rafael Lima', 'Advogado'], ['Beatriz Santos', 'Assistente jurídica']] as [$name, $role]) {
                DB::table('members')->insert(['office_id' => $office, 'name' => $name, 'email' => '', 'role' => $role, 'account_type' => $role === 'Administradora' ? 'admin' : 'associate', 'created_at' => now(), 'updated_at' => now()]);
            }
            $clients = [];
            foreach (['Mariana Torres', 'Rafael Antunes', 'Ana Costa', 'Carlos Oliveira'] as $name) {
                $clients[] = Client::create(['office_id' => $office, 'name' => $name, 'notes' => 'Cadastro fictício para apresentação do sistema.']);
            }
            $cases = [];
            foreach ([['Ação de indenização', '0012345-67.2026.8.26.0100', '3ª Vara Cível · São Paulo', 'Em andamento'],
                ['Revisão contratual', null, 'Consultivo · São Paulo', 'Em andamento'],
                ['Ação de cobrança', '0023410-89.2026.8.26.0100', '3ª Vara Cível · São Paulo', 'Aguardando audiência'],
                ['Inventário extrajudicial', null, 'Tabelionato de Notas · São Paulo', 'Concluído']] as $index => [$title, $number, $court, $status]) {
                $cases[] = LegalCase::create(['office_id' => $office, 'client_id' => $clients[$index]->id, 'title' => $title, 'number' => $number,
                    'court' => $court, 'status' => $status, 'responsible' => 'Helena Souza', 'notes' => 'Dados fictícios. Nenhuma consulta ao tribunal foi realizada.']);
            }
            $today = now()->startOfDay();
            foreach ([['Protocolar contestação — Autos 0012345-67', 'Mariana Torres · 3ª Vara Cível', 0, 17, 'Alta', 0],
                ['Revisar minuta de contrato de locação', 'Rafael Antunes · Revisão contratual', 1, 18, 'Média', 1],
                ['Organizar documentos da perícia', 'Mariana Torres · Ação de indenização', 2, 14, 'Baixa', 0],
                ['Confirmar audiência de conciliação', 'Ana Costa · 3ª Vara Cível', 3, 9, 'Média', 2],
                ['Enviar relatório de andamento ao cliente', 'Ana Costa · Ação de cobrança', 3, 16, 'Baixa', 2]] as [$title, $context, $days, $hour, $priority, $caseIndex]) {
                Task::create(['office_id' => $office, 'legal_case_id' => $cases[$caseIndex]->id, 'title' => $title,
                    'context' => $context, 'due_at' => $today->copy()->addDays($days)->setHour($hour), 'priority' => $priority]);
            }
            foreach ([['Revisar petição inicial', 0], ['Atualizar cadastro de Rafael Antunes', 1], ['Separar documentos para audiência', 2], ['Conferir procuração assinada', 2], ['Preparar relatório de andamento', 2]] as $index => [$title, $caseIndex]) {
                Task::create(['office_id' => $office, 'legal_case_id' => $cases[$caseIndex]->id, 'title' => $title,
                    'context' => $clients[$caseIndex]->name, 'due_at' => $today->copy()->setHour(12), 'priority' => 'Baixa',
                    'completed_at' => $today->copy()->setTime(9, 12 + $index * 8)]);
            }
            foreach ([['Contestação', 0, 17, 0], ['Revisão do contrato de locação', 1, 18, 1], ['Documentos para a audiência', 2, 17, 2]] as [$title, $days, $hour, $caseIndex]) {
                DB::table('deadlines')->insert(['office_id' => $office, 'legal_case_id' => $cases[$caseIndex]->id, 'title' => $title,
                    'due_at' => $today->copy()->addDays($days)->setHour($hour), 'created_at' => now(), 'updated_at' => now()]);
            }
            Appointment::create(['office_id' => $office, 'legal_case_id' => $cases[0]->id, 'title' => 'Reunião com Mariana Torres', 'kind' => 'Reunião', 'location' => 'Escritório · Sala de reunião', 'starts_at' => $today->copy()->addDay()->setTime(14, 30)]);
            Appointment::create(['office_id' => $office, 'legal_case_id' => $cases[2]->id, 'title' => 'Audiência de conciliação', 'kind' => 'Audiência', 'location' => '3ª Vara Cível · São Paulo', 'starts_at' => $today->copy()->addDays(3)->setTime(10, 0)]);
            foreach (['Petição inicial revisada.', 'Cadastro de Rafael Antunes atualizado.', 'Documentos para audiência separados.'] as $index => $description) {
                DB::table('activities')->insert(['office_id' => $office, 'description' => $description, 'actor' => 'Helena Souza', 'kind' => 'task', 'created_at' => $today->copy()->setTime(9, 12 - $index * 4)]);
            }
            foreach (['Olá, podemos confirmar nossa reunião para amanhã?', 'Encaminhei os documentos solicitados. Obrigado.', 'Aguardo a confirmação da audiência.'] as $index => $body) {
                DB::table('messages')->insert(['office_id' => $office, 'client_id' => $clients[$index]->id, 'body' => $body, 'outgoing' => false, 'created_at' => $today->copy()->setTime(8, 30), 'updated_at' => now()]);
            }
        });
    }
}
