# Changelog

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
