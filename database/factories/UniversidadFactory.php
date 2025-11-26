<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Universidad>
 */
class UniversidadFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'codigo' => $this->faker->unique()->numerify('UNI###'),
            'nombre' => $this->faker->company . ' University',
            'tipo_gestion' => $this->faker->randomElement(['PUBLICO', 'PRIVADO']),
            'estado_licenciamiento' => $this->faker->randomElement(['LICENCIA_OTORGADA', 'LICENCIA_DENEGADA', 'EN_PROCESO', 'NINGUNO']),
        ];
    }
}
