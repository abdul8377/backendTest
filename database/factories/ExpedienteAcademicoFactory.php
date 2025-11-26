<?php

namespace Database\Factories;

use App\Models\EpSede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpedienteAcademico>
 */
class ExpedienteAcademicoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'ep_sede_id' => EpSede::factory(),
            'codigo_estudiante' => $this->faker->unique()->numerify('202#####'),
            'grupo' => $this->faker->randomElement(['A', 'B', 'C']),
            'ciclo' => $this->faker->numberBetween(1, 10),
            'correo_institucional' => $this->faker->safeEmail,
            'estado' => 'ACTIVO',
            'rol' => 'ESTUDIANTE',
            'vigente_desde' => $this->faker->date(),
            'vigente_hasta' => $this->faker->optional()->date(),
        ];
    }
}
