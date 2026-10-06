<?php

namespace Database\Factories;

use App\Models\Client;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
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
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
        ];
    }
}
