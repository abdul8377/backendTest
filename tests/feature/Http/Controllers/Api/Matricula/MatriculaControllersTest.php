<?php

namespace Tests\Feature\Http\Controllers\Api\Matricula;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Matricula, EpSede, PeriodoAcademico, EscuelaProfesional, Sede, Facultad, Universidad, ExpedienteAcademico};
use Laravel\Sanctum\Sanctum;

// Grupo 4: Matrícula

class MatriculaControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_index_returns_matriculas()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'matricula.read', 'guard_name' => 'web']);
        $user->givePermissionTo('matricula.read');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/matriculas');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_store_creates_matricula()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'matricula.create', 'guard_name' => 'web']);
        $user->givePermissionTo('matricula.create');
        Sanctum::actingAs($user);

        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);
        $epSede = EpSede::factory()->create(['escuela_profesional_id' => $escuela->id, 'sede_id' => $sede->id]);
        $periodo = PeriodoAcademico::factory()->create();
        $estudiante = User::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $estudiante->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO'
        ]);

        $data = [
            'user_id' => $estudiante->id,
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
            'tipo' => 'REGULAR'
        ];

        $response = $this->postJson('/api/matriculas', $data);

        $response->assertCreated();
    }

    /** @test */
    public function test_show_returns_matricula_details()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'matricula.read', 'guard_name' => 'web']);
        $user->givePermissionTo('matricula.read');
        Sanctum::actingAs($user);

        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);
        $epSede = EpSede::factory()->create(['escuela_profesional_id' => $escuela->id, 'sede_id' => $sede->id]);
        $periodo = PeriodoAcademico::factory()->create();

        $matricula = Matricula::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id
        ]);

        $response = $this->getJson("/api/matriculas/{$matricula->id}");

        $response->assertOk()
            ->assertJson(['data' => ['id' => $matricula->id]]);
    }
}

class MatriculaEstudianteControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_mis_matriculas_returns_student_enrollments()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/estudiante/mis-matriculas');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }
}
