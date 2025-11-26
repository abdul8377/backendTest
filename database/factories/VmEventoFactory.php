<?php

namespace Database\Factories;

use App\Models\VmEvento;
use App\Models\PeriodoAcademico;
use App\Models\EpSede;
use App\Models\VmCategoriaEvento;
use Illuminate\Database\Eloquent\Factories\Factory;

class VmEventoFactory extends Factory
{
    protected $model = VmEvento::class;

    public function definition(): array
    {
        return [
            'periodo_id' => PeriodoAcademico::factory(),
            'targetable_type' => EpSede::class,
            'targetable_id' => EpSede::factory(),
            'categoria_evento_id' => null,
            'codigo' => 'EVT-' . fake()->unique()->numberBetween(1000, 9999),
            'titulo' => fake()->sentence(4),
            'subtitulo' => fake()->sentence(6),
            'descripcion_corta' => fake()->sentence(10),
            'descripcion_larga' => fake()->paragraph(3),
            'modalidad' => fake()->randomElement(['PRESENCIAL', 'VIRTUAL', 'MIXTA']),
            'lugar_detallado' => fake()->address(),
            'url_imagen_portada' => null,
            'url_enlace_virtual' => null,
            'estado' => 'PLANIFICADO',
            'requiere_inscripcion' => false,
            'cupo_maximo' => null,
            'inscripcion_desde' => null,
            'inscripcion_hasta' => null,
        ];
    }

    public function conInscripcion(): static
    {
        return $this->state(fn(array $attributes) => [
            'requiere_inscripcion' => true,
            'cupo_maximo' => fake()->numberBetween(20, 100),
            'inscripcion_desde' => now()->subDays(7)->toDateString(),
            'inscripcion_hasta' => now()->addDays(7)->toDateString(),
        ]);
    }

    public function enCurso(): static
    {
        return $this->state(fn(array $attributes) => [
            'estado' => 'EN_CURSO',
        ]);
    }

    public function cerrado(): static
    {
        return $this->state(fn(array $attributes) => [
            'estado' => 'CERRADO',
        ]);
    }
}
