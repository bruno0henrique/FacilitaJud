# Requisitos e decisões

- Referência principal: projeto Lovable fornecido pelo usuário. Foram analisados Painel, Tarefas, Processos, Agenda, Prazos, Clientes, Documentos, Equipe, Mensagens e Configurações; Sora, superfícies claras arredondadas, lavanda, navegação lateral e organização modular foram mantidos.
- Dashboard: uma ação operacional maior e dois apoios menores. Listas de tarefas, prazos e atividade preservam a composição aprovada na imagem enviada.
- Concluir tarefa: checkbox, risco horizontal suave, contraste reduzido, legibilidade e permanência imediata na lista; sem efeitos gamificados. Filtros de concluídas permitem rever e reabrir.
- Stack obrigatória: PHP/Laravel, Python, PostgreSQL e Docker. Python fica separado para integrações; o núcleo usa Laravel. Neon e Neon Auth são os serviços online iniciais.
- Apresentação inicial: 07/10/2026. Dados de demonstração explicitamente fictícios, relacionados entre clientes, processos, tarefas, prazos e agenda.
- Portabilidade: mesmo código e migrations para Neon e PostgreSQL local. Funcionar localmente não significa sincronização offline; autenticação offline não faz parte da versão 0.1.0.
- Produção: autenticação obrigatória, escritório por identidade, demonstração desabilitada, chaves fora do Git, sessão e cache no banco. Documentos armazenados no banco para independência do disco efêmero.
- Publicação: projeto Vercel `facilitajud` criado e preset Container configurado; conectar PostgreSQL e Neon Auth e homologar antes de declarar a versão online funcional.
- Judit provável e futura: nenhuma consulta implementada ou resultado judicial simulado como integração real.
