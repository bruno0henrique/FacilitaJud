<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Office;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
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
            'name' => 'Anotações.txt',
            'path' => 'database',
            'mime' => 'text/plain',
            'size' => 5,
            'contents' => base64_encode('teste'),
        ];
    }
}
