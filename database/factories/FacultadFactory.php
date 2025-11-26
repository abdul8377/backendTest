<?php

namespace Database\Factories;

use App\Models\Universidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Facultad>
 */
class FacultadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'universidad_id' => Universidad::factory(),
            'codigo' => $this->faker->unique()->numerify('FAC###'),
            'nombre' => $this->faker->words(3, true),
        ];
    }
}
