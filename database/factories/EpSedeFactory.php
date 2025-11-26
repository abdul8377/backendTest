<?php

namespace Database\Factories;

use App\Models\EscuelaProfesional;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EpSede>
 */
class EpSedeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        file_put_contents('debug_log.txt', "EpSedeFactory: start\n", FILE_APPEND);
        $ep = EscuelaProfesional::factory();
        file_put_contents('debug_log.txt', "EpSedeFactory: EP factory created\n", FILE_APPEND);
        $sede = Sede::factory();
        file_put_contents('debug_log.txt', "EpSedeFactory: Sede factory created\n", FILE_APPEND);
        return [
            'escuela_profesional_id' => $ep,
            'sede_id' => $sede,
            'vigente_desde' => $this->faker->date(),
            'vigente_hasta' => $this->faker->optional()->date(),
        ];
    }
}
