# Changelog

## 0.3.7 — 2026-10-07

- Botão Testar o programa no login, com carregamento, usuário Demo e escritório preenchido isolado por sessão, disponível localmente e em produção sem Neon Auth.
- Sessão de teste de quatro horas, saída da conta, isolamento de registros e bloqueio da aceitação de convites do ambiente de teste por contas reais.
- Diagnóstico de falhas de conexão do Neon Auth sem registrar credenciais.

## 0.3.6 — 2026-10-07

- Removida a reação ao cursor do fundo do login; as ondas continuam animadas automaticamente.

## 0.3.5 — 2026-10-07

- Fundo do login com ondas e dithering da referência enviada, adaptados para WebGL sem novas dependências, na paleta lavanda da marca.
- Renderização limitada a 24 quadros por segundo e resolução controlada, pausa em abas ocultas, preferência por movimento reduzido e fundo estático quando WebGL não estiver disponível.

## 0.3.4 — 2026-10-07

- Login, cadastro e alteração de senha apresentam progresso; login mantém o estado até a navegação e bloqueia envios repetidos.
- Falhas de autenticação permanecem visíveis no formulário, sem recarregar a tela e apagar o retorno.
- Carga de apresentação vinculada ao escritório autorizado, com 24 processos, 48 tarefas, 120 obrigações, documentos, agenda e histórico coerentes.
- Carga idempotente preserva cadastros e andamentos existentes; não preenche outras contas automaticamente.
- Remove rótulos decorativos de dados fictícios do rodapé e da sidebar.

## 0.3.3 — 2026-10-07

- Renova a sessão considerando o vencimento do token, inclusive depois de navegar entre módulos, sem duplicar chamadas em andamento.
- Adiciona teste da renovação em navegação, alternância de abas e demonstração.

## 0.3.2 — 2026-10-07

- Reduz consultas por módulo; Equipe deixa de carregar processos, documentos, mensagens e filas não utilizadas.
- Impede renovação Neon na demonstração e sem sessão; agrupa chamadas ao alternar abas.
- Aproxima aplicação e banco em São Paulo, ajusta OPcache e caches Laravel em produção.
- Documenta a configuração de certificados HTTPS no PHP nativo do Windows.
- Comprime respostas textuais e mantém assets estáticos em cache, sem cache público de informações do escritório.

## 0.3.1 — 2026-10-06

- Padroniza a grafia visível da marca como FacilitaJud na sidebar e no login.
- Define a atribuição de processos por planilha, com o mockup como referência provisória até o modelo final ficar pronto; remove a opção manual.
- Mantém a atribuição de tarefas e compromissos e corrige o espaçamento entre os blocos de Equipe em desktop e mobile.

## 0.3.0 — 2026-10-06

- Nova identidade com as imagens originais da marca, Satoshi variável servida localmente e paleta pastel centralizada.
- Cadastro comum cria um administrador; convites vinculam associados ao escritório com acessos definidos pelo ADM.
- Categorias personalizadas, responsabilidades e permissões individuais editáveis na Equipe, com efeito na próxima requisição.
- Associados visualizam apenas registros atribuídos e módulos autorizados, com restrições aplicadas às páginas, aos detalhes e às APIs.
- ADM atribui processos, tarefas e compromissos; importação, redistribuição e exportação de planilhas continuam exclusivas do administrador.

## 0.2.2 — 2026-10-06

- O próximo passo considera obrigações importadas e permite registrar o andamento diretamente pela home.


## 0.2.1 — 2026-10-06

- Alinha o cabeçalho do proxy Laravel ao contrato de sessão do SDK oficial Neon Auth.

## 0.2.0 — 2026-10-06

- Autenticação Neon pelo Laravel, com cookies protegidos no servidor e tratamento de cadastro já existente.
- Importação Excel com prévia, escolha de colunas e atribuição de obrigações a funcionários.
- Fila diária, histórico de andamentos, conclusão, redistribuição e exportação preservando a planilha original.
- Convites de equipe com expiração e acesso restrito às obrigações atribuídas.
- Home com próximo passo em largura completa e dois cards: Prazos hoje e Compromissos hoje, com contagens diárias.
- Cabeçalho de Prazos simplificado, números destacados e frase inferior da sidebar removida.


## 0.1.1 — 2026-10-06

- Exclui dependências locais, configurações privadas e caches do envio à Vercel.
- Reconstrói o cache de providers dentro da imagem Docker sem referências a dependências de desenvolvimento.
- Banco Neon preparado com as migrations e configurações de produção vinculadas à Vercel.

## 0.1.0 — 2026-10-06

- Primeira versão Laravel do FacilitaJud, preservando sidebar, tipografia Sora e paleta pastel da referência Lovable.
- Home com uma ação principal e dois blocos de apoio: prazos e agenda.
- Tarefas com conclusão reversível, risco discreto e persistência; concluir tarefa não encerra prazo jurídico.
- Cadastros de clientes, processos, tarefas, compromissos e prazos, detalhes vinculados e documentos privados em PostgreSQL.
- Validação, sessão no servidor, isolamento de escritórios e integração implementada com Neon Auth (homologação real pendente).
- Docker local/produção, suporte PostgreSQL local/Neon e serviço Python preparado para integração futura.
- Equipe em consulta e mensagens demonstrativas; Judit não conectado.
