<?php

namespace Tests\Feature\Http\Controllers\Api\Vm;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, VmProyecto, EpSede, PeriodoAcademico, EscuelaProfesional, Sede, Facultad, Universidad};
use Laravel\Sanctum\Sanctum;

class ProyectoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected EpSede $epSede;
    protected PeriodoAcademico $periodo;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Permission::create(['name' => 'vm.proyecto.read', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.proyecto.create', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.proyecto.update', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'vm.proyecto.delete', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'ep.manage.ep_sede', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->givePermissionTo(['vm.proyecto.read', 'vm.proyecto.create', 'vm.proyecto.update', 'vm.proyecto.delete', 'ep.manage.ep_sede']);
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
    public function test_index_returns_proyectos()
    {
        VmProyecto::factory()->count(3)->create([
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id
        ]);

        $response = $this->getJson('/api/vm/proyectos');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_store_creates_proyecto_vinculado()
    {
        $data = [
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id,
            'codigo' => 'PROY-001',
            'titulo' => 'Proyecto de Salud Comunitaria',
            'descripcion' => 'Descripción del proyecto',
            'tipo' => 'VINCULADO',
            'modalidad' => 'PRESENCIAL',
            'niveles' => [1, 2],
            'horas_planificadas' => 100
        ];

        $response = $this->postJson('/api/vm/proyectos', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('vm_proyectos', [
            'titulo' => 'Proyecto de Salud Comunitaria',
            'codigo' => 'PROY-001',
            'tipo' => 'VINCULADO'
        ]);
    }

    /** @test */
    public function test_store_creates_proyecto_libre()
    {
        $data = [
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id,
            'codigo' => 'PROY-002',
            'titulo' => 'Proyecto Libre',
            'descripcion' => 'Descripción',
            'tipo' => 'LIBRE',
            'modalidad' => 'VIRTUAL',
            'horas_planificadas' => 50
        ];

        $response = $this->postJson('/api/vm/proyectos', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('vm_proyectos', [
            'titulo' => 'Proyecto Libre',
            'tipo' => 'LIBRE'
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson('/api/vm/proyectos', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'ep_sede_id',
                'periodo_id',
                'titulo',
                'tipo',
                'modalidad',
                'horas_planificadas'
            ]);
    }

    /** @test */
    public function test_store_requires_niveles_for_vinculado()
    {
        $data = [
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id,
            'titulo' => 'Test',
            'tipo' => 'VINCULADO',
            'modalidad' => 'PRESENCIAL',
            'horas_planificadas' => 100
        ];

        $response = $this->postJson('/api/vm/proyectos', $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['niveles']);
    }

    /** @test */
    public function test_show_returns_proyecto_details()
    {
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id
        ]);

        $response = $this->getJson("/api/vm/proyectos/{$proyecto->id}");

        $response->assertOk();
        // Avoid assertJson to prevent ArraySubset error
        $this->assertEquals($proyecto->id, $response->json('data.proyecto.id'));
    }

    /** @test */
    public function test_destroy_deletes_proyecto()
    {
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $this->epSede->id,
            'periodo_id' => $this->periodo->id,
            'estado' => 'PLANIFICADO'
        ]);

        $response = $this->deleteJson("/api/vm/proyectos/{$proyecto->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('vm_proyectos', ['id' => $proyecto->id]);
    }

    /** @test */
    public function test_endpoints_require_permissions()
    {
        $userSinPermisos = User::factory()->create();
        Sanctum::actingAs($userSinPermisos);

        $this->getJson('/api/vm/proyectos')->assertForbidden();
        $this->postJson('/api/vm/proyectos', [])->assertForbidden();
    }
}
