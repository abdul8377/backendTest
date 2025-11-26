<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PeriodoAcademico>
 */
class PeriodoAcademicoFactory extends Factory
{
    private static int $sequence = 0;

    public function definition(): array
    {
        // Generar combinaciones únicas de año/ciclo
        self::$sequence++;
        $anio = 2020 + (int) floor(self::$sequence / 2);
        $ciclo = (self::$sequence % 2) + 1;

        return [
            'codigo' => "{$anio}-{$ciclo}",
            'anio' => $anio,
            'ciclo' => $ciclo,
            'estado' => $this->faker->randomElement(['PLANIFICADO', 'EN_CURSO', 'CERRADO']),
            'es_actual' => false,
            'fecha_inicio' => $this->faker->date(),
            'fecha_fin' => $this->faker->date(),
        ];
    }
}
