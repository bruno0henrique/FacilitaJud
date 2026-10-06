<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\LegalCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LegalCase>
 */
class LegalCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'office_id' => fn (array $attributes): int => Client::findOrFail($attributes['client_id'])->office_id,
            'title' => 'Ação de indenização',
            'court' => '3ª Vara Cível · São Paulo',
            'status' => 'Em andamento',
            'responsible' => fake()->name(),
        ];
    }
}
