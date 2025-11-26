<?php

namespace Database\Factories;

use App\Models\Universidad;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sede>
 */
class SedeFactory extends Factory
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
            'nombre' => $this->faker->city . ' Campus',
            'es_principal' => $this->faker->boolean(20),
            'esta_suspendida' => $this->faker->boolean(5),
        ];
    }
}
