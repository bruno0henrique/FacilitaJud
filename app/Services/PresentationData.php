<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use ZipArchive;

class PresentationData
{
    public function populate(int $officeId): array
    {
        return DB::transaction(function () use ($officeId): array {
            $office = DB::table('offices')->where('id', $officeId)->lockForUpdate()->first();
            if (! $office) {
                throw new RuntimeException('Escritório não encontrado.');
            }
            if ($office->is_demo) {
                DB::table('clients')->where('office_id', $officeId)->where('notes', 'Cadastro fictício para apresentação do sistema.')->update(['notes' => 'Documentação centralizada no escritório.']);
                DB::table('legal_cases')->where('office_id', $officeId)->where('notes', 'Dados fictícios. Nenhuma consulta ao tribunal foi realizada.')->update(['notes' => 'Acompanhamento interno do processo. Conferir documentos e próximos passos.']);
            }
            if (DB::table('spreadsheet_imports')->where('office_id', $officeId)->where('mapping->presentation_dataset', 'v1')->exists()) {
                return ['created' => false];
            }
            $today = now()->startOfDay();
            $stamp = ['created_at' => now(), 'updated_at' => now()];
            $members = DB::table('members')->where('office_id', $officeId)->orderBy('id')->get();
            if ($members->isEmpty()) {
                throw new RuntimeException('Cadastre o administrador antes de preparar a apresentação.');
            }
            foreach ([['Camila Azevedo', 'Advogada', 'Revisar peças e acompanhar audiências.'], ['Pedro Valença', 'Assistente jurídico', 'Conferir documentos, custas e movimentações.']] as [$name, $role, $responsibilities]) {
                if (! $members->contains('name', $name)) {
                    DB::table('members')->insert($stamp + ['office_id' => $officeId, 'name' => $name, 'email' => '', 'role' => $role, 'account_type' => 'associate', 'responsibilities' => $responsibilities]);
                }
            }
            $members = DB::table('members')->where('office_id', $officeId)->orderBy('id')->get();
            $names = ['Luiza Monteiro', 'Gabriel Farias', 'Renata Tavares', 'Eduardo Nogueira', 'Patrícia Lemos', 'André Vasconcelos', 'Clara Mendonça', 'Felipe Duarte', 'Sofia Barros', 'Rodrigo Meireles', 'Juliana Prado', 'Henrique Freitas', 'Cecília Matos', 'Daniel Siqueira', 'Isabela Ramos', 'Thiago Peixoto', 'Laura Fonseca', 'Marcelo Pires', 'Beatriz Albuquerque', 'Vinícius Rezende', 'Ateliê Horizonte Ltda.', 'Mercado Vila Serena Ltda.', 'Clínica Jardim Sul Ltda.', 'Oficina Rota Nova Ltda.'];
            $subjects = [
                ['Indenização por danos materiais', 'Conferir comprovantes de despesas', 'Revisar contestação', 'Documentos recebidos. Conferir valores e organizar os comprovantes por data.'],
                ['Cobrança de aluguéis', 'Conferir parcelas e encargos', 'Preparar memória de cálculo', 'Contrato e demonstrativo recebidos. Aguardando conciliação dos pagamentos.'],
                ['Obrigação de fazer', 'Conferir cumprimento da decisão', 'Revisar petição de cumprimento', 'Cliente encaminhou documentos complementares. Revisar antes do protocolo.'],
                ['Revisão de contrato', 'Conferir cláusulas e anexos', 'Revisar minuta de acordo', 'Minuta em revisão interna. Confirmar condições com o cliente.'],
                ['Execução de título extrajudicial', 'Conferir saldo e custas', 'Atualizar demonstrativo do débito', 'Planilha de cálculo recebida. Conferir atualização e despesas processuais.'],
                ['Rescisão contratual', 'Organizar documentação do contrato', 'Preparar manifestação', 'Instrumento contratual e comunicações organizados para análise.'],
            ];
            $cases = [];
            foreach ($names as $index => $name) {
                $client = DB::table('clients')->where('office_id', $officeId)->where('name', $name)->value('id');
                $client ??= DB::table('clients')->insertGetId($stamp + ['office_id' => $officeId, 'name' => $name, 'email' => 'contato'.($index + 1).'@example.test', 'notes' => 'Prefere contato por e-mail. Documentação centralizada no escritório.']);
                $subject = $subjects[$index % count($subjects)];
                $member = $members[$index % $members->count()];
                $sequence = sprintf('%07d', 9100001 + $index);
                $base = $sequence.'2026826010000';
                $remainder = 0;
                foreach (str_split($base) as $digit) {
                    $remainder = ($remainder * 10 + (int) $digit) % 97;
                }
                $number = $sequence.'-'.sprintf('%02d', 98 - $remainder).'.2026.8.26.0100';
                $case = DB::table('legal_cases')->where('office_id', $officeId)->where('number', $number)->value('id');
                $case ??= DB::table('legal_cases')->insertGetId($stamp + ['office_id' => $officeId, 'client_id' => $client, 'title' => $subject[0].' · '.$name, 'number' => $number, 'court' => ($index % 5 + 1).'ª Vara Cível · São Paulo', 'status' => $index % 7 === 0 ? 'Aguardando audiência' : 'Em andamento', 'responsible' => $member->name, 'assigned_member_id' => $member->id, 'notes' => $subject[3]]);
                $cases[] = ['id' => $case, 'client_id' => $client, 'name' => $name, 'number' => $number, 'member' => $member, 'subject' => $subject];
                foreach ([0, 1] as $taskIndex) {
                    DB::table('tasks')->insert($stamp + ['office_id' => $officeId, 'legal_case_id' => $case, 'assigned_member_id' => $member->id, 'title' => $subject[$taskIndex + 1].' · '.$name, 'context' => $name.' · '.($index % 5 + 1).'ª Vara Cível', 'due_at' => $today->copy()->addDays($index % 5)->setTime($taskIndex ? 17 : 14, 0), 'priority' => ['Alta', 'Média', 'Baixa'][$index % 3], 'completed_at' => $index % 4 === 0 ? $today->copy()->setTime(9, 10 + $taskIndex * 10) : null]);
                }
                if ($index < 12) {
                    DB::table('deadlines')->insert($stamp + ['office_id' => $officeId, 'legal_case_id' => $case, 'title' => $subject[2].' · '.$name, 'due_at' => $today->copy()->addDays($index % 4)->setTime(17, 0)]);
                    DB::table('appointments')->insert($stamp + ['office_id' => $officeId, 'legal_case_id' => $case, 'assigned_member_id' => $member->id, 'title' => ($index % 3 === 0 ? 'Audiência de conciliação' : 'Reunião de acompanhamento').' · '.$name, 'kind' => $index % 3 === 0 ? 'Audiência' : 'Reunião', 'location' => $index % 3 === 0 ? ($index % 5 + 1).'ª Vara Cível · São Paulo' : 'Videoconferência · Escritório', 'starts_at' => $today->copy()->addDays($index % 6)->setTime(10 + $index % 5, 30)]);
                }
                $content = "Roteiro de conferência — {$name}\n{$subject[0]}\n\n{$subject[3]}\n\nConferir: identificação das partes, procuração, anexos, datas e comprovantes.\nResponsável: {$member->name}\n";
                DB::table('documents')->insert($stamp + ['office_id' => $officeId, 'legal_case_id' => $case, 'name' => 'Roteiro de conferência — '.$name.'.txt', 'path' => 'database', 'mime' => 'text/plain; charset=UTF-8', 'size' => strlen($content), 'contents' => base64_encode($content)]);
                if ($index < 8) {
                    DB::table('messages')->insert($stamp + ['office_id' => $officeId, 'client_id' => $client, 'body' => ['Encaminhei os comprovantes solicitados. Podemos confirmar o recebimento?', 'Podemos agendar uma reunião para revisar os próximos passos?', 'A procuração assinada já foi enviada. Aguardo a confirmação.'][$index % 3], 'outgoing' => false]);
                }
            }
            $rows = [['Obrigação', 'Prazo', 'Processo', 'Contexto']];
            $items = [];
            foreach (range(0, 119) as $index) {
                $case = $cases[$index % count($cases)];
                $title = ['Conferir movimentação e registrar andamento', 'Conferir custas e comprovantes', 'Revisar documentação pendente', 'Atualizar resumo do processo', 'Conferir intimações e próximos passos'][intdiv($index, count($cases))];
                $due = $today->copy()->addDays($index < 80 ? 0 : 1 + $index % 4)->setTime(16 + $index % 2, 0);
                $rows[] = [$title, $due->toDateTimeString(), $case['number'], $case['name'].' · '.$case['subject'][0]];
                $items[] = $stamp + ['office_id' => $officeId, 'source_row' => $index + 2, 'assigned_member_id' => $case['member']->id, 'title' => $title, 'process_number' => $case['number'], 'context' => $case['name'].' · '.$case['subject'][0], 'due_at' => $due, 'status' => $index < 24 ? 'Concluído' : ($index % 6 === 0 ? 'Em andamento' : 'Pendente'), 'note' => $index < 24 ? 'Documentação e movimentações conferidas. Resumo atualizado; nenhuma pendência de conferência.' : null, 'completed_at' => $index < 24 ? $today->copy()->setTime(9, $index) : null];
            }
            $contents = $this->workbook($rows);
            $import = DB::table('spreadsheet_imports')->insertGetId($stamp + ['office_id' => $officeId, 'filename' => 'Rotina de acompanhamento.xlsx', 'checksum' => hash('sha256', $contents), 'contents' => base64_encode($contents), 'sheet' => 'Rotina', 'header_row' => 1, 'mapping' => json_encode(['title' => 'A', 'due_at' => 'B', 'process_number' => 'C', 'context' => 'D', 'presentation_dataset' => 'v1'])]);
            foreach ($items as $index => $item) {
                $id = DB::table('work_items')->insertGetId($item + ['spreadsheet_import_id' => $import]);
                if ($item['note']) {
                    $member = $cases[$index % count($cases)]['member'];
                    DB::table('work_item_updates')->insert(['work_item_id' => $id, 'member_id' => $member->id, 'actor' => $member->name, 'status' => 'Concluído', 'note' => $item['note'], 'created_at' => $item['completed_at']]);
                }
            }
            foreach (array_slice($cases, 0, 8) as $index => $case) {
                DB::table('activities')->insert(['office_id' => $officeId, 'description' => 'Conferência de documentos registrada · '.$case['name'], 'actor' => $case['member']->name, 'kind' => 'document', 'created_at' => $today->copy()->setTime(9, 5 + $index * 4)]);
            }

            return ['created' => true, 'clients' => 24, 'cases' => 24, 'tasks' => 48, 'work_items' => 120, 'documents' => 24];
        });
    }

    private function workbook(array $rows): string
    {
        $file = tempnam(sys_get_temp_dir(), 'facilitajud');
        try {
            $zip = new ZipArchive;
            if ($zip->open($file, ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Não foi possível preparar a planilha.');
            }
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
            $zip->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
            $zip->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Rotina" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
            $xml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            foreach ($rows as $index => $row) {
                $xml .= '<row r="'.($index + 1).'">';
                foreach ($row as $column => $value) {
                    $xml .= '<c r="'.chr(65 + $column).($index + 1).'" t="inlineStr"><is><t>'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
                }
                $xml .= '</row>';
            }
            $zip->addFromString('xl/worksheets/sheet1.xml', $xml.'</sheetData></worksheet>');
            $zip->close();

            return file_get_contents($file);
        } finally {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }
}
