<?php

namespace Database\Factories;

use App\Models\EpSede;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EpSedeStaffHistorial>
 */
class EpSedeStaffHistorialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'ep_sede_id' => EpSede::factory(),
            'user_id' => User::factory(),
            'role' => $this->faker->randomElement(['COORDINADOR', 'ENCARGADO']),
            'evento' => $this->faker->randomElement(['ASSIGN', 'UNASSIGN', 'REINSTATE', 'DELEGATE']),
            'desde' => $this->faker->date(),
            'hasta' => $this->faker->optional()->date(),
            'actor_id' => User::factory(),
            'motivo' => $this->faker->sentence,
        ];
    }
}
