# FacilitaJud

Versão **0.3.5** · Laravel 13 / PHP 8.5 · PostgreSQL · Docker · serviço Python 3.14 opcional.

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
