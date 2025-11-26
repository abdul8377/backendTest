<?php

namespace Tests\Feature\Http\Controllers\Api\Academico;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, ExpedienteAcademico, EpSede, EscuelaProfesional, Sede, Facultad, Universidad};
use Laravel\Sanctum\Sanctum;

class ExpedienteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EpSede $epSede;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear permisos y roles
        \Spatie\Permission\Models\Permission::create(['name' => 'ep.manage.ep_sede', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo('ep.manage.ep_sede');
        Sanctum::actingAs($this->user);

        // Crear estructura académica
        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);

        $this->epSede = EpSede::factory()->create([
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);
    }

    /** @test */
    public function test_store_creates_new_expediente()
    {
        $estudiante = User::factory()->create();

        $data = [
            'user_id' => $estudiante->id,
            'ep_sede_id' => $this->epSede->id,
            'codigo_estudiante' => '2024001',
            'grupo' => 'A',
            'correo_institucional' => 'estudiante@universidad.edu',
            'rol' => 'ESTUDIANTE'
        ];

        $response = $this->postJson('/api/academico/expedientes', $data);

        $response->assertCreated()
            ->assertJson([
                'ok' => true,
                'data' => [
                    'user_id' => $estudiante->id,
                    'ep_sede_id' => $this->epSede->id,
                    'codigo_estudiante' => '2024001',
                    'estado' => 'ACTIVO'
                ]
            ]);

        $this->assertDatabaseHas('expedientes_academicos', [
            'user_id' => $estudiante->id,
            'ep_sede_id' => $this->epSede->id,
            'codigo_estudiante' => '2024001',
            'estado' => 'ACTIVO'
        ]);
    }

    /** @test */
    public function test_store_updates_existing_expediente_if_duplicate()
    {
        $estudiante = User::factory()->create();

        // Crear expediente existente
        $existing = ExpedienteAcademico::factory()->create([
            'user_id' => $estudiante->id,
            'ep_sede_id' => $this->epSede->id,
            'codigo_estudiante' => '2024001',
            'grupo' => 'A',
            'estado' => 'SUSPENDIDO'
        ]);

        // Intentar crear otro con mismo user_id y ep_sede_id
        $data = [
            'user_id' => $estudiante->id,
            'ep_sede_id' => $this->epSede->id,
            'codigo_estudiante' => '2024002', // Diferente código
            'grupo' => 'B',
            'correo_institucional' => 'nuevo@universidad.edu'
        ];

        $response = $this->postJson('/api/academico/expedientes', $data);

        $response->assertOk() // 200, no 201
            ->assertJson([
                'ok' => true,
                'data' => [
                    'id' => $existing->id,
                    'codigo_estudiante' => '2024002', // Actualizado
                    'grupo' => 'B', // Actualizado
                    'estado' => 'ACTIVO' // Reactivado
                ]
            ]);

        // Verificar que solo existe un expediente
        $this->assertEquals(1, ExpedienteAcademico::where('user_id', $estudiante->id)
            ->where('ep_sede_id', $this->epSede->id)
            ->count());
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson('/api/academico/expedientes', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['user_id', 'ep_sede_id']);
    }

    /** @test */
    public function test_store_requires_permission()
    {
        // Usuario sin permiso
        $userSinPermiso = User::factory()->create();
        Sanctum::actingAs($userSinPermiso);

        $data = [
            'user_id' => User::factory()->create()->id,
            'ep_sede_id' => $this->epSede->id
        ];

        $response = $this->postJson('/api/academico/expedientes', $data);

        $response->assertForbidden()
            ->assertJson([
                'ok' => false,
                'message' => 'NO_AUTORIZADO'
            ]);
    }

    /** @test */
    public function test_store_sets_default_values()
    {
        $estudiante = User::factory()->create();

        $data = [
            'user_id' => $estudiante->id,
            'ep_sede_id' => $this->epSede->id
            // Sin rol, vigente_desde, etc.
        ];

        $response = $this->postJson('/api/academico/expedientes', $data);

        $response->assertCreated();

        $expediente = ExpedienteAcademico::where('user_id', $estudiante->id)->first();

        $this->assertEquals('ESTUDIANTE', $expediente->rol); // Default
        $this->assertEquals('ACTIVO', $expediente->estado);
        $this->assertNotNull($expediente->vigente_desde); // Auto-set
        $this->assertNull($expediente->vigente_hasta);
    }
}
