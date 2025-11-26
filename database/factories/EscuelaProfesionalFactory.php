<?php

namespace Database\Factories;

use App\Models\Facultad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EscuelaProfesional>
 */
class EscuelaProfesionalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'facultad_id' => Facultad::factory(),
            'codigo' => $this->faker->unique()->numerify('EP###'),
            'nombre' => $this->faker->jobTitle,
        ];
    }
}
