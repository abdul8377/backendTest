<?php

namespace Database\Factories;

use App\Models\VmProyecto;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VmProceso>
 */
class VmProcesoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'proyecto_id' => VmProyecto::factory(),
            'nombre' => $this->faker->sentence,
            'descripcion' => $this->faker->paragraph,
            'tipo_registro' => $this->faker->randomElement(['HORAS', 'ASISTENCIA', 'EVALUACION', 'MIXTO']),
            'horas_asignadas' => $this->faker->numberBetween(1, 20),
            'nota_minima' => null,
            'requiere_asistencia' => false,
            'orden' => $this->faker->numberBetween(1, 10),
            'estado' => 'PLANIFICADO',
        ];
    }
}
