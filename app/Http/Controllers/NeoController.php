<?php

namespace App\Http\Controllers;

use App\Services\WorkspacePermissions;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class NeoController extends Controller
{
    private const TOPICS = [
        'contestacao' => 'contestação', 'apelacao' => 'apelação', 'recurso' => 'recursos processuais',
        'audiencia' => 'audiência', 'conciliacao' => 'conciliação', 'custas' => 'custas processuais',
        'prova' => 'produção de provas', 'execucao' => 'execução', 'peticao' => 'petição',
        'tutela' => 'tutela de urgência', 'procuracao' => 'procuração', 'prescricao' => 'prescrição',
        'contrato' => 'contratos', 'prazo' => 'prazos processuais', 'ata' => 'ata de reunião',
    ];

    public function chat(Request $request): StreamedResponse
    {
        $data = $request->validate(['message' => 'required|string|max:1000']);
        $text = str_replace('reunioes', 'reuniao', Str::lower(Str::ascii($data['message'])));
        $navigation = preg_match('/\b(abrir|ir|ver|mostrar|meus|minhas|hoje|onde)\b/', $text);
        $moduleNames = ['painel' => 'Painel', 'tarefa' => 'Tarefas', 'processo' => 'Processos', 'agenda' => 'Agenda', 'reuniao' => 'Reuniões', 'prazo' => 'Prazos', 'cliente' => 'Clientes', 'documento' => 'Documentos', 'equipe' => 'Equipe', 'mensage' => 'Mensagens', 'configurac' => 'Configurações'];
        $moduleKeys = ['tarefa' => 'tarefas', 'processo' => 'processos', 'reuniao' => 'reunioes', 'prazo' => 'prazos', 'cliente' => 'clientes', 'documento' => 'documentos', 'mensage' => 'mensagens', 'configurac' => 'configuracoes'];
        $local = null;
        $link = null;
        if ($navigation) {
            foreach ($moduleNames as $word => $name) {
                if (str_contains($text, $word)) {
                    $module = $moduleKeys[$word] ?? $word;
                    if (app(WorkspacePermissions::class)->module($request, $module)) {
                        $local = 'Abra '.$name.' para consultar e atualizar os registros disponíveis ao seu perfil. Não consulto dados pessoais do escritório.';
                        $link = ['url' => route('workspace', ['module' => $module]), 'label' => 'Abrir '.$name];
                    } else {
                        $local = 'Seu perfil não tem acesso a esse módulo. Solicite ao administrador a revisão dos seus acessos.';
                    }
                    break;
                }
            }
        }
        $topics = [];
        foreach (self::TOPICS as $word => $label) {
            if (preg_match('/\b'.preg_quote($word, '/').'[a-z]*\b/', $text)) {
                $topics[] = $label;
            }
        }
        $topics = array_slice($topics, 0, 3);
        if (! $local && ! $topics) {
            $local = 'Posso explicar temas como contestação, audiência, custas e prazos processuais, ou ajudar a abrir os módulos. Faça uma pergunta geral, sem nomes, documentos ou dados pessoais.';
        }
        if (! $local && ! config('services.openai.key')) {
            $local = 'A conexão do Neo ainda não está disponível. Você pode usar os módulos normalmente. Tente a orientação jurídica novamente após a configuração do serviço.';
        }
        // Somente rótulos fixos entram no prompt; nunca o texto livre do usuário.
        $prompt = 'Explique de maneira geral, no contexto jurídico brasileiro: '.implode(', ', $topics).'. Inclua um próximo passo genérico de verificação. Não analise um caso concreto.';

        return response()->stream(function () use ($local, $link, $prompt): void {
            $emit = static function (string $event, array $payload): void {
                echo 'event: '.$event."\n".'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)."\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };
            $emit('status', ['text' => $local ? 'Preparando orientação…' : 'Preparando explicação jurídica…']);
            if ($local) {
                foreach (mb_str_split($local, 24) as $part) {
                    $emit('delta', ['text' => $part]);
                }
                $emit('done', ['link' => $link]);

                return;
            }
            $body = null;
            try {
                $response = Http::withToken(config('services.openai.key'))->accept('text/event-stream')
                    ->connectTimeout(10)->timeout(45)->withOptions(['stream' => true])
                    ->post('https://api.openai.com/v1/responses', [
                        'model' => config('services.openai.model'), 'store' => false, 'stream' => true,
                        'max_output_tokens' => 350, 'instructions' => file_get_contents(base_path('NEO.md')), 'input' => $prompt,
                    ]);
                if (! $response->successful()) {
                    $emit('error', ['text' => 'Não foi possível consultar o Neo agora. Tente novamente em instantes.']);

                    return;
                }
                $body = $response->toPsrResponse()->getBody();
                $completed = false;
                while (! $body->eof() && ! connection_aborted()) {
                    $line = trim(Utils::readLine($body, 65536));
                    if (! str_starts_with($line, 'data: ')) {
                        continue;
                    }
                    $event = json_decode(substr($line, 6), true);
                    if (! is_array($event)) {
                        continue;
                    }
                    if (($event['type'] ?? '') === 'response.output_text.delta') {
                        $emit('delta', ['text' => $event['delta'] ?? '']);
                    } elseif (($event['type'] ?? '') === 'response.completed') {
                        $completed = true;
                        $emit('done', []);
                        break;
                    } elseif (in_array($event['type'] ?? '', ['error', 'response.failed', 'response.incomplete'], true)) {
                        break;
                    }
                }
                if (! $completed && ! connection_aborted()) {
                    $emit('error', ['text' => 'A resposta foi interrompida. Tente novamente para continuar.']);
                }
            } catch (Throwable) {
                $emit('error', ['text' => 'O Neo está indisponível neste momento. Tente novamente.']);
            } finally {
                $body?->close();
            }
        }, 200, ['Content-Type' => 'text/event-stream', 'Cache-Control' => 'no-cache, no-store', 'X-Accel-Buffering' => 'no']);
    }
}
