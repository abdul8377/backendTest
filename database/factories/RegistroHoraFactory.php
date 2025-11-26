<?php

namespace Database\Factories;

use App\Models\RegistroHora;
use App\Models\EpSede;
use Illuminate\Database\Eloquent\Factories\Factory;

class RegistroHoraFactory extends Factory
{
    protected $model = RegistroHora::class;

    public function definition(): array
    {
        return [
            'expediente_id'   => 1,                          // el test puede sobreescribir
            'ep_sede_id'      => EpSede::factory(),          // ✅ evita el NOT NULL
            'periodo_id'      => null,                       // el test lo setea
            'fecha'           => $this->faker->date(),
            'minutos'         => $this->faker->numberBetween(10, 120),
            'estado'          => 'APROBADO',
            'vinculable_type' => 'custom.item',
            'vinculable_id'   => 1,
            'actividad'       => $this->faker->sentence(3),
        ];
    }
}
