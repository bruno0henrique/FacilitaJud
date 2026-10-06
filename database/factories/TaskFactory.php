<?php

namespace Database\Factories;

use App\Models\Office;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'office_id' => Office::factory(),
            'title' => 'Revisar contestação',
            'context' => 'Conferir documentos do processo',
            'due_at' => now()->addDay()->setTime(17, 0),
            'priority' => 'Alta',
        ];
    }
}
