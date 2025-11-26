<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            // Campos obligatorios de tu migración:
            'username'   => $this->faker->unique()->userName(),
            'first_name' => $this->faker->firstName(),
            'last_name'  => $this->faker->lastName(),
            'email'      => $this->faker->unique()->safeEmail(),
            'password'   => Hash::make('password'),

            // Los demás tienen default o son nullable en DB:
            'failed_login_attempts' => 0,
            'login_blocked_until'   => null,
            'profile_photo'         => null,
            'status'                => 'active',
            'doc_tipo'              => null,
            'doc_numero'            => null,
            'celular'               => null,
            'pais'                  => null,
            'religion'              => null,
            'fecha_nacimiento'      => null,
            // ⚠️ NO remember_token porque tu tabla no lo tiene
        ];
    }
}
