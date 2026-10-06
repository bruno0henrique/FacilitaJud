# FacilitaJud

Versão **0.1.0** · Laravel 13 / PHP 8.5 · PostgreSQL · Docker · serviço Python 3.14 opcional.

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

## Online: Vercel + Neon

Projeto Vercel: **facilitajud**, preset **Container**, raiz `./`, Dockerfile `Dockerfile.vercel`. Repositório: https://github.com/bruno0henrique/FacilitaJud. A imagem inclui Apache/PHP e os assets compilados, atende a variável `PORT` e não depende de Node em produção.

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
