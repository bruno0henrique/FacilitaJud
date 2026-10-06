<?php

return [
    'required' => 'Preencha o campo :attribute.',
    'string' => 'O campo :attribute deve conter texto.',
    'email' => 'Informe um e-mail válido.',
    'date' => 'Informe uma data e um horário válidos em :attribute.',
    'boolean' => 'Selecione uma opção válida em :attribute.',
    'in' => 'Selecione uma opção válida em :attribute.',
    'exists' => 'O registro selecionado em :attribute não está disponível no seu escritório.',
    'unique' => 'Este :attribute já está cadastrado.',
    'regex' => 'Confira o formato de :attribute.',
    'file' => 'Selecione um arquivo válido.',
    'mimes' => 'Use um arquivo PDF, Word, texto ou imagem JPG/PNG.',
    'uploaded' => 'Não foi possível enviar o arquivo. Confira o tamanho e tente novamente.',
    'max' => ['string' => 'Use até :max caracteres em :attribute.', 'file' => 'O arquivo deve ter até 10 MB.'],
    'attributes' => ['title' => 'título', 'name' => 'nome', 'email' => 'e-mail', 'due_at' => 'prazo',
        'starts_at' => 'horário', 'priority' => 'prioridade', 'context' => 'contexto', 'legal_case_id' => 'processo',
        'client_id' => 'cliente', 'number' => 'número do processo', 'court' => 'vara / tribunal',
        'status' => 'situação', 'kind' => 'tipo', 'location' => 'local', 'phone' => 'telefone', 'notes' => 'observações',
        'file' => 'arquivo', 'completed' => 'conclusão', 'reminders' => 'lembretes', 'display_name' => 'nome de exibição'],
];
