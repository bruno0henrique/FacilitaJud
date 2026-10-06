<?php

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
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
            'title' => 'Reunião com cliente',
            'kind' => 'Reunião',
            'location' => 'Escritório',
            'starts_at' => now()->addDay()->setTime(14, 30),
        ];
    }
}
