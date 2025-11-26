<?php

namespace Database\Factories;

use App\Models\VmProceso;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\VmSesion>
 */
class VmSesionFactory extends Factory
{
    public function definition(): array
    {
        return [
            // Relación polimórfica - usa el alias del morph map
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => VmProceso::factory(),

            // Datos de la sesión
            'fecha' => $this->faker->date(),
            'hora_inicio' => '09:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'PLANIFICADO',
        ];
    }
}
