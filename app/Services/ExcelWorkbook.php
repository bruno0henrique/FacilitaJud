<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Validation\ValidationException;

class ExcelWorkbook
{
    public function run(array $input): array
    {
        $result = Process::input(json_encode($input, JSON_THROW_ON_ERROR))->timeout(60)
            ->run([PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3', base_path('integrations/excel.py')]);
        $data = json_decode($result->output(), true);
        if (! $result->successful() || isset($data['error']) || ! is_array($data)) {
            throw ValidationException::withMessages(['file' => $data['error'] ?? 'Não foi possível ler o Excel. Use um arquivo .xlsx válido.']);
        }

        return $data;
    }
}
