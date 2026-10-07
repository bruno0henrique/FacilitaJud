<?php

namespace App\Console\Commands;

use App\Services\PresentationData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('facilitajud:prepare-presentation {--email= : E-mail do administrador} {--demo : Escritório local de apresentação}')]
#[Description('Acrescenta registros da apresentação ao escritório selecionado, sem substituir dados existentes')]
class PreparePresentation extends Command
{
    public function handle(PresentationData $data): int
    {
        if ((bool) $this->option('demo') === (bool) $this->option('email')) {
            $this->error('Informe somente --email ou --demo.');

            return self::FAILURE;
        }
        if ($this->option('demo')) {
            if (! app()->environment('local', 'testing') || ! config('facilitajud.demo')) {
                $this->error('A apresentação local está desabilitada.');

                return self::FAILURE;
            }
            $office = DB::table('offices')->where('is_demo', true)->value('id');
        } else {
            $office = DB::table('members')->where('email', $this->option('email'))->where('account_type', 'admin')->value('office_id');
        }
        if (! $office) {
            $this->error('Escritório do administrador não encontrado.');

            return self::FAILURE;
        }
        $result = $data->populate((int) $office);
        $this->info($result['created'] ? 'Registros adicionados ao escritório selecionado.' : 'O escritório já foi preparado. Nenhum registro duplicado.');

        return self::SUCCESS;
    }
}
