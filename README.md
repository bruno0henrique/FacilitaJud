# FacilitaJud

Versão **0.3.11** · Laravel 13 / PHP 8.5 · PostgreSQL · Docker · serviço Python 3.14 opcional.

Sistema jurídico com painel operacional, tarefas, processos, clientes, prazos, agenda e documentos. A identidade pastel e a estrutura modular preservam a referência Lovable; o topo usa **uma ação principal e dois apoios**. Equipe mostra os membros cadastrados; mensagens são uma demonstração, sem envio externo.

## Executar localmente com Docker

1. Copie `.env.example` para `.env` e defina uma senha própria em `DB_PASSWORD`.
2. Execute `docker compose build app`.
3. Gere a chave: `docker compose run --rm --no-deps app php artisan key:generate --show`. Copie o resultado para `APP_KEY` no `.env` (não versione a chave).
4. Execute `docker compose up -d db app`.
5. Execute `docker compose exec app php artisan migrate --no-interaction`.
6. Para a apresentação com dados fictícios: `docker compose exec app php artisan db:seed --no-interaction`.
7. Abra http://localhost:8000.

O modo demonstrativo exige `APP_ENV=local`, `DEMO_MODE=true` e acesso local. O Compose publica as portas somente em `127.0.0.1`. Não publique esse modo na internet. `DEMO_DOCKER_LOOPBACK` permite o encaminhamento local do Docker; nunca habilita a demonstração em produção.

Os volumes `postgres_data` e `app_storage` persistem os dados. Não use `docker compose down -v` se quiser preservá-los. Para o serviço Python: `docker compose --profile integrations up -d --build python`; saúde em http://localhost:8001/health. A conexão Judit não está implementada nesta versão.

## Desenvolvimento sem container para o PHP

Requer PHP 8.5 com PDO PostgreSQL, mbstring, fileinfo, openssl, curl, intl, zip e sodium; Composer 2; Node 24. Execute `composer install`, `npm ci`, `npm run build`, `docker compose up -d db`, `php artisan key:generate`, `php artisan migrate --seed` e `php artisan serve`. O PostgreSQL local fica na porta 55432.

No PHP nativo do Windows, configure `curl.cainfo` e `openssl.cafile` no `php.ini` para um bundle de certificados atualizado (https://curl.se/ca/cacert.pem) e reinicie o servidor. Sem isso, o Neon pode falhar com cURL 60. Não desative a verificação HTTPS. O Docker já instala os certificados do sistema.

## Online: Vercel + Neon

Projeto Vercel: **facilitajud**, preset **Container**, raiz `./`, Dockerfile `Dockerfile.vercel`. Repositório: https://github.com/bruno0henrique/FacilitaJud. A imagem inclui Apache/PHP e os assets compilados, atende a variável `PORT` e não depende de Node em produção.

A região da aplicação é São Paulo (`gru1`), próxima ao Neon. O container ajusta o OPcache e prepara os caches de configuração, rotas e views na inicialização em produção. Assets com hash recebem cache longo; páginas e dados de usuários não recebem cache público. Os módulos carregam somente os dados usados na tela, mantendo as permissões consultadas a cada requisição. A renovação Neon ocorre somente com sessão real e evita chamadas repetidas ao alternar abas.

Use `.env.production.example` como lista das configurações, sem publicar esse arquivo com valores reais. Defina `APP_KEY` persistente, `APP_ENV=production`, `APP_DEBUG=false`, `DEMO_MODE=false`, `APP_URL` HTTPS, sessão criptografada e cookie seguro. Integre o projeto Neon na Vercel:

- `DATABASE_URL`: conexão PostgreSQL pooled com SSL para a aplicação.
- `DATABASE_URL_UNPOOLED`: conexão direta para migrations; não altere o pool de produção para migrar.
- `NEON_AUTH_BASE_URL`: endpoint do Neon Auth da branch usada nesta instalação.

Execute as migrations com a URL direta em ambiente administrativo protegido: substitua temporariamente `DATABASE_URL` pela conexão direta e rode `php artisan migrate --force --no-interaction`. Não rode seeder demonstrativo em produção. Depois publique com `vercel --prod` ou por push na branch de produção conectada.

No Neon Auth, habilite e-mail/senha e adicione o domínio publicado às origens permitidas. Cadastro cria um escritório pessoal vazio; token assinado Ed25519 é validado no Laravel e trocado por sessão no servidor. Login, cadastro, recuperação de senha e saída estão implementados, mas **o fluxo real depende da configuração do Neon e precisa ser homologado com a conta real**. Não grave tokens no localStorage nem compartilhe chaves em commits.

## Portabilidade e próximos passos

A mesma aplicação usa Neon ou PostgreSQL local através de variáveis de ambiente, com as mesmas migrations. Os documentos ficam no banco nesta entrega (até 10 MB por arquivo), pois o disco da Vercel não oferece persistência para uploads. Em um servidor local, `DOCUMENT_STORAGE=filesystem` permite usar o volume persistente para novos arquivos; os documentos anteriores continuam disponíveis no banco. Planejar object storage antes de aumentar o volume de documentos.

Neon Auth exige internet mesmo quando a aplicação roda em servidor local. A demonstração local funciona sem essa conexão; autenticação totalmente offline e sincronização entre bases ainda não estão implementadas. A troca futura de provedor deve preservar o vínculo entre identidade e escritório e exigir migração explícita das contas.

Judit fica como integração futura no serviço Python, com filas, histórico de sincronizações, limites, webhooks autenticados e armazenamento das credenciais no servidor. Nenhuma consulta, cálculo automático de prazo ou monitoramento processual é feito nesta versão. Convites de equipe, permissões por função e mensagens reais também ficam para uma próxima entrega.

## Validar

`php artisan test --compact`, `php vendor/bin/pint --dirty --format agent`, `npm run build`, `npm audit --omit=dev` e `docker compose build app`.

Os testes cobrem persistência e reabertura de tarefas, independência de prazos jurídicos, cadastros relacionados, validação, isolamento de escritórios, upload/download em banco, bloqueio da demonstração em produção e verificação criptográfica do Neon com respostas simuladas. Eles não substituem a homologação do Neon online.


### Obrigações por planilha

Em Prazos, o administrador importa `.xlsx` (10 MB, até 10.000 linhas), escolhe aba, linha dos títulos e as colunas de obrigação, data, processo e contexto. A confirmação atribui as linhas ao responsável escolhido. Datas aceitas: células de data do Excel, `dd/mm/aaaa` e ISO. Arquivos `.xls` devem ser salvos como `.xlsx`.

O funcionário entra pelo convite gerado em Equipe, com o e-mail convidado, e recebe somente sua fila. Convites valem sete dias; contas já vinculadas a outro escritório não são transferidas automaticamente. Compartilhe o link diretamente: o sistema não envia convites por e-mail.

Cada andamento exige uma observação e mantém histórico, autor e situação. A conclusão atualiza o resumo diário. Alterações concorrentes são recusadas para evitar sobrescrita. O administrador pode redistribuir as linhas selecionadas e baixar o Excel atualizado. O download preserva células, abas, fórmulas e estilos originais e acrescenta colunas `FacilitaJud` com responsável, situação, último andamento e atualização. O arquivo aberto no computador não é alterado automaticamente.

A rotina de leitura/exportação usa Python com biblioteca padrão dentro do container. Em execução PHP nativa, `python` (Windows) ou `python3` (Linux) precisa estar no PATH. A integração Judit permanece planejada; nenhuma consulta processual externa é simulada como real.

### Sessão Neon

Cadastro, login, recuperação e renovação passam por endpoints do Laravel no mesmo domínio. Cookies do Neon ficam criptografados na sessão do servidor, e os JWTs são verificados por JWKS. Senhas e tokens não são devolvidos ao navegador. Cadastro sem sessão imediata apresenta a confirmação de criação e orienta a entrada, evitando repetir o cadastro.


### Equipe e acessos (0.3.0)

Cadastro sem convite cria um escritório e um administrador. Convites gerados na Equipe criam associados no escritório do convidante; o link vale por 7 dias e só pode ser aceito pelo e-mail convidado. O envio é feito compartilhando o link; não há envio automático de e-mail implementado.

O ADM cria e edita categorias com responsabilidades e permissões. Cada associado pode herdar a categoria ou receber acessos personalizados. Ao remover a personalização, volta a herdar a categoria. Alterações são consultadas no banco a cada requisição, inclusive durante sessões existentes. Sem categoria/personalização, o acesso básico permite consultar e registrar andamentos das próprias obrigações; um conjunto vazio de permissões restringe o acesso ao Painel e ao próprio perfil. O tipo de conta administrativo é explícito e não depende do nome da categoria ou da função.

Tarefas e compromissos são atribuídos pelo ADM em Equipe → Atribuir tarefas e compromissos. Processos serão atribuídos por planilha: o mockup enviado serve como referência provisória, e o modelo final ainda está em definição. A opção manual de processos foi retirada; a importação desse modelo não está habilitada. Clientes, documentos e conversas são limitados aos clientes/processos atribuídos; atividades gerais e prazos jurídicos do escritório ficam restritos ao ADM. Uma atribuição não libera o módulo automaticamente: a permissão de consulta também precisa estar habilitada. Criar registros gerais, configurar o escritório e importar/distribuir/exportar Excel são ações administrativas. Para tarefas, o associado pode editar/concluir quando autorizado; documentos podem ser adicionados apenas a processos atribuídos e autorizados. A vinculação automática entre processos e linhas de planilha/Judit permanece futura.

### Identidade visual

Os PNGs enviados foram preservados em `public/brand`, usando enquadramento proporcional na interface. A home mantém um bloco operacional principal e dois secundários com números do dia. Tokens globais de cores e tipografia ficam em `resources/css/app.css`.

Satoshi é obtida da [Fontshare/Indian Type Foundry](https://www.fontshare.com/fonts/satoshi), conforme a [ITF Free Font License](https://www.fontshare.com/licenses/itf-ffl). O build baixa o WOFF2 oficial para `public/fonts` (ignorado pelo Git), e o Docker incorpora o arquivo. O navegador carrega a fonte do próprio servidor. A primeira instalação/build precisa de internet; depois de preparada, a fonte funciona localmente sem acessar a Fontshare. Os arquivos da fonte não são redistribuídos pelo repositório. Pesos: 400 para texto, 500/600 para hierarquia e 700 para números.

No ambiente local, a demonstração é exibida apenas sem sessão autenticada. Após entrar, prevalecem o escritório e as permissões reais da conta.

A grafia exibida é **FacilitaJud**, inclusive no login e na sidebar. O símbolo dos arquivos originais foi preservado; o nome é apresentado em texto com a capitalização aprovada.

### Apresentação preenchida

`php artisan facilitajud:prepare-presentation --demo` acrescenta dados ao escritório local. Para um escritório de conta autenticada previamente autorizado: `php artisan facilitajud:prepare-presentation --email=EMAIL_DO_ADMINISTRADOR`. A carga inclui 24 processos, 48 tarefas, 120 obrigações, 24 documentos de texto, 12 compromissos e 12 prazos, com vínculos e histórico. Uma segunda execução preserva as alterações e não duplica a carga. `PRESENTATION_EMAIL` permite preparar somente o escritório do administrador com esse e-mail ao entrar; mantenha vazio para contas de uso real. Os conteúdos são sintéticos, sem consultas a tribunais ou envio de mensagens. A planilha incluída demonstra a fila de obrigações, sem definir o futuro modelo de importação de processos.

O botão **Testar o programa** cria um escritório isolado por sessão, com dados sintéticos e usuário Demo, sem depender do Neon Auth. Acesso válido por quatro horas, com edições restritas ao próprio escritório. Convites desse ambiente não vinculam contas reais. `TRIAL_ENABLED=false` desabilita a entrada pública; `DEMO_MODE` continua exclusivo do desenvolvimento local.

### Interações dos módulos (0.3.8)

Clientes: 15 registros por página; processos e obrigações: 25, com busca em todas as páginas e navegação em português. Clientes, processos e compromissos podem ser editados pelo ADM; o painel oferece histórico completo de atividades.

Mensagens permite buscar clientes, escolher conversas e registrar textos no próprio escritório. Não há entrega externa nem respostas automáticas. Associados precisam das permissões de consulta e envio e só acessam clientes de processos atribuídos.

Equipe permite editar nome, função, categoria, responsabilidades e acessos, remover e restaurar associados. A remoção revoga sessões existentes sem apagar registros ou histórico. O convidado aparece na equipe antes de aceitar; a aceitação vincula a conta ao mesmo cadastro.

Agenda alterna lista e calendário mensal, com navegação, detalhes e criação por dia. Google Calendar, Apple e Outlook são opções visuais em desenvolvimento. Neo apresenta chat por texto e voz como protótipo visual: não chama IA, não ativa o microfone e não executa alterações.

### Reuniões e gravação (0.3.9)

Eventos de agenda do tipo **Reunião** aparecem no módulo Reuniões. O ADM vê o escritório; associados precisam de `reunioes.view` e só acessam eventos atribuídos. `reunioes.record` autoriza gravar e editar anotações/ata. A ciência dos termos é registrada por membro e versão; a confirmação de que os participantes foram informados e concordam é exigida em cada início. Esse registro não substitui a comunicação aos participantes.

A captura utiliza [MediaRecorder](https://developer.mozilla.org/en-US/docs/Web/API/MediaRecorder), microfone mono e taxa solicitada de 24 kbps (Opus/WebM, Opus/Ogg ou AAC/MP4 conforme suporte). A taxa efetiva depende do navegador. Só há áudio: não gravamos câmera, tela ou áudio de chamadas automaticamente. HTTPS é necessário online; localhost também é permitido. Em videoconferências com fones, a voz remota pode não chegar ao microfone.

Trechos são produzidos a cada 15 segundos, divididos em até 256 KB e enviados na ordem, com checksum e repetição idempotente. O banco armazena os bytes em base64, como o armazenamento de documentos atual (aproximadamente 33% de acréscimo); quatro horas a 24 kbps representam cerca de 43 MB de áudio antes desse acréscimo. Não há corte por duração de reunião; o teto de segurança é 256 MB de áudio por gravação, e é possível iniciar outra gravação na mesma reunião. Não há dependência de discos temporários de hospedagem nem serviço adicional para rodar localmente.

Ao falhar o envio, a captura é encerrada; os trechos pendentes permanecem na memória desta página para tentar novamente, e os já enviados permanecem no banco. Não feche a aba enquanto o salvamento estiver pendente: o navegador exibirá um aviso, mas não pode garantir a recuperação de trechos ainda não enviados após fechar ou perder energia. Gravações abandonadas preservam áudio parcial; após 10 minutos sem envio, um novo início encerra o registro anterior como interrompido. A reprodução usa streaming com HTTP Range, autenticado e sem cache público.

Transcrição automática, resumo, ata e sugestões de tarefas/prazos aguardam a conexão com IA. A interface identifica essa condição, permite anotações/ata manuais e não envia áudio a provedores externos nem modifica prazos. A futura integração deverá conservar a separação de escritórios e exigir revisão humana antes de aplicar sugestões. O tom Opus em `tests/Fixtures/meeting-tone.webm` é gerado sinteticamente e usado apenas nos testes de integridade/reprodução, sem gravações de pessoas.

## Neo e janela de gravação — 0.3.10

Configure `OPENAI_API_KEY` no servidor (Vercel → Settings → Environment Variables) e faça redeploy; localmente use `.env`. `OPENAI_MODEL` é opcional, padrão `gpt-4.1-mini`. A chave nunca é enviada ao navegador. O Compose carrega as variáveis pelo `.env`.

As regras estão em [NEO.md](NEO.md). Para cumprir o bloqueio de dados sensíveis, somente temas fixos reconhecidos são enviados à OpenAI: o texto original, o histórico e os registros do escritório nunca são transmitidos. Navegação é resolvida localmente e respeita o perfil. Não há ferramentas, leitura de documentos/áudios ou alteração automática de registros. Perguntas fora da lista recebem orientação local. A integração usa `store: false`; isso não representa garantia de ausência de retenção operacional pelo provedor. Referências: [streaming Responses](https://developers.openai.com/api/docs/guides/streaming-responses) e [modelo GPT-4.1 mini](https://developers.openai.com/api/docs/models/gpt-4.1-mini).

A gravação abre em uma janela independente; manter essa janela aberta permite navegar no sistema sem parar a captura. Fechar ou recarregar a janela de gravação encerra o microfone: o navegador mostra um aviso enquanto houver áudio pendente. Participantes e ciência são registrados por gravação; aceite dos termos fica vinculado ao membro e à versão e pode ser consultado em Configurações. Nenhum áudio é enviado ao Neo. Transcrição/ata automática permanece em desenvolvimento.

Verificação adicional: `node --test tests/neo-chat.test.mjs tests/meeting-recorder.test.mjs tests/neon-auth.test.mjs`. Os testes PHP incluem uploads multipart com sequência textual, reprodução real de Opus, isolamento de áudio e inspeção do payload enviado à IA com dados pessoais de teste e instrução maliciosa.

### Registro de gravação — 0.3.11

Após salvar no pop-up, o servidor confirma o estado do áudio e registra a atividade. A lista da reunião é sincronizada por um canal limitado ao escritório e usuário; voltar à janela ou abrir uma reunião também consulta o registro no servidor. Anotações ainda não salvas e áudio em reprodução são preservados. O link Ver gravação na reunião mantém a reunião selecionada e a página de origem. Capturas vazias não são anunciadas como áudio salvo.

Resumo e ata por IA aparecem após o salvamento, marcados Em desenvolvimento. O botão informa o estado localmente e não envia áudio nem transcrições ao Neo ou à OpenAI. Testes de segurança cobrem permissões, sessão expirada, CSRF, XSS, validação de upload, isolamento de áudio e finalização idempotente; não representam uma auditoria externa de segurança.
