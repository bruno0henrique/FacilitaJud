# Changelog

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
