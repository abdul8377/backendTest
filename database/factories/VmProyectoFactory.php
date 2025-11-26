<?php

namespace Database\Factories;

use App\Models\EpSede;
use App\Models\PeriodoAcademico;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VmProyecto>
 */
class VmProyectoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ep_sede_id' => EpSede::factory(),
            'periodo_id' => PeriodoAcademico::factory(),
            'codigo' => $this->faker->unique()->numerify('PROY-####'),
            'titulo' => $this->faker->sentence,
            'descripcion' => $this->faker->paragraph,
            'tipo' => $this->faker->randomElement(['VINCULADO', 'LIBRE', 'PROYECTO']),
            'modalidad' => $this->faker->randomElement(['PRESENCIAL', 'VIRTUAL', 'MIXTA']),
            'estado' => 'PLANIFICADO',
            'horas_planificadas' => $this->faker->numberBetween(10, 100),
            'horas_minimas_participante' => $this->faker->numberBetween(5, 50),
        ];
    }
}
