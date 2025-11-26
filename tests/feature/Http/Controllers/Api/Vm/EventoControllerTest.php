<?php

namespace Tests\Feature\Http\Controllers\Api\Vm;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, VmEvento, EpSede, PeriodoAcademico, EscuelaProfesional, Sede, Facultad, Universidad};
use Laravel\Sanctum\Sanctum;

class EventoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EpSede $epSede;
    protected PeriodoAcademico $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Permission::create(['name' => 'vm.evento.read', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.evento.create', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.evento.update', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.evento.delete', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['vm.evento.read', 'vm.evento.create', 'vm.evento.update', 'vm.evento.delete']);
        Sanctum::actingAs($this->user);

        $universidad = \App\Models\Universidad::factory()->create();
        $facultad = \App\Models\Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = \App\Models\EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = \App\Models\Sede::factory()->create(['universidad_id' => $universidad->id]);

        $this->epSede = \App\Models\EpSede::factory()->create([
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);

        // Crear expediente de COORDINADOR para autorizar EpScopeService
        \App\Models\ExpedienteAcademico::factory()->create([
            'user_id' => $this->user->id,
            'ep_sede_id' => $this->epSede->id,
            'rol' => 'COORDINADOR',
            'estado' => 'ACTIVO'
        ]);

        $this->periodo = PeriodoAcademico::factory()->create();
    }

    /** @test */
    public function test_index_returns_eventos()
    {
        VmEvento::factory()->count(3)->create([
            'periodo_id' => $this->periodo->id,
            'targetable_id' => $this->epSede->id,
            'targetable_type' => 'App\\Models\\EpSede'
        ]);

        $response = $this->getJson('/api/vm/eventos');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_store_creates_evento()
    {
        $data = [
            'periodo_id' => $this->periodo->id,
            'codigo' => 'EVT-001',
            'titulo' => 'Charla de Salud Mental',
            'subtitulo' => 'Para estudiantes',
            'descripcion_corta' => 'Descripción breve',
            'modalidad' => 'PRESENCIAL',
            'requiere_inscripcion' => true,
            'cupo_maximo' => 50,
            'targetable_id' => $this->epSede->id,
            'targetable_type' => 'App\\Models\\EpSede',
            'sesiones' => [
                [
                    'fecha' => now()->addDays(7)->toDateString(),
                    'hora_inicio' => '10:00',
                    'hora_fin' => '12:00'
                ]
            ]
        ];

        $response = $this->postJson('/api/vm/eventos', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('vm_eventos', [
            'titulo' => 'Charla de Salud Mental',
            'codigo' => 'EVT-001'
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson('/api/vm/eventos', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'periodo_id',
                'titulo',
                'modalidad',
                'sesiones'
            ]);
    }

    /** @test */
    public function test_store_requires_at_least_one_sesion()
    {
        $data = [
            'periodo_id' => $this->periodo->id,
            'titulo' => 'Evento Test',
            'modalidad' => 'PRESENCIAL',
            'sesiones' => []  // Vacío
        ];

        $response = $this->postJson('/api/vm/eventos', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sesiones']);
    }

    /** @test */
    public function test_show_returns_evento_details()
    {
        $evento = VmEvento::factory()->create([
            'periodo_id' => $this->periodo->id,
            'targetable_id' => $this->epSede->id,
            'targetable_type' => 'App\\Models\\EpSede'
        ]);

        $response = $this->getJson("/api/vm/eventos/{$evento->id}");

        $response->assertOk();
        // Avoid assertJson to prevent ArraySubset error
        $this->assertEquals($evento->id, $response->json('data.id'));
    }

    /** @test */
    public function test_destroy_deletes_evento()
    {
        $evento = VmEvento::factory()->create([
            'periodo_id' => $this->periodo->id,
            'targetable_id' => $this->epSede->id,
            'targetable_type' => 'App\\Models\\EpSede',
            'estado' => 'PLANIFICADO'
        ]);

        $response = $this->deleteJson("/api/vm/eventos/{$evento->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('vm_eventos', ['id' => $evento->id]);
    }

    /** @test */
    public function test_endpoints_require_permissions()
    {
        $userSinPermisos = User::factory()->create();
        Sanctum::actingAs($userSinPermisos);

        $this->getJson('/api/vm/eventos')->assertForbidden();
        $this->postJson('/api/vm/eventos', [])->assertForbidden();
    }
}
