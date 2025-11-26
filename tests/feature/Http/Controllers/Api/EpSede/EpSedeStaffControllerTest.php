<?php

namespace Tests\Feature\Http\Controllers\Api\EpSede;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, EpSede, EscuelaProfesional, Sede, Facultad, Universidad, ExpedienteAcademico};
use Laravel\Sanctum\Sanctum;

class EpSedeStaffControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected EpSede $epSede;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear roles
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'COORDINADOR', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'ENCARGADO', 'guard_name' => 'web']);

        // Crear permisos
        \Spatie\Permission\Models\Permission::create(['name' => 'ep.manage.coordinador', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Permission::create(['name' => 'ep.manage.encargado', 'guard_name' => 'web']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('ADMINISTRADOR');
        $this->admin->givePermissionTo(['ep.manage.coordinador', 'ep.manage.encargado']);
        Sanctum::actingAs($this->admin);

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
    public function test_context_returns_user_and_panel_info()
    {
        $response = $this->getJson('/api/ep-sedes/staff/context');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'username', 'email', 'roles'],
                'panel_mode',
                'can_manage_coordinador',
                'can_manage_encargado',
                'is_admin',
                'ep_sedes'
            ]);
    }

    /** @test */
    public function test_current_returns_current_staff()
    {
        $response = $this->getJson("/api/ep-sedes/{$this->epSede->id}/staff");

        $response->assertOk()
            ->assertJsonStructure([
                'coordinador',
                'encargado'
            ]);
    }

    /** @test */
    public function test_history_returns_staff_changes()
    {
        $response = $this->getJson("/api/ep-sedes/{$this->epSede->id}/staff/history");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [],
                'links',
                'meta'
            ]);
    }

    /** @test */
    public function test_assign_assigns_coordinador_to_ep_sede()
    {
        $coordinador = User::factory()->create();

        // Crear expediente para el coordinador
        ExpedienteAcademico::factory()->create([
            'user_id' => $coordinador->id,
            'ep_sede_id' => $this->epSede->id,
            'rol' => 'COORDINADOR',
            'estado' => 'ACTIVO'
        ]);

        $data = [
            'user_id' => $coordinador->id,
            'role' => 'COORDINADOR'
        ];

        $response = $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/assign", $data);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        // Verificar que el usuario tiene el rol
        $this->assertTrue($coordinador->fresh()->hasRole('COORDINADOR'));
    }

    /** @test */
    public function test_assign_assigns_encargado_to_ep_sede()
    {
        $encargado = User::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $encargado->id,
            'ep_sede_id' => $this->epSede->id,
            'rol' => 'ENCARGADO',
            'estado' => 'ACTIVO'
        ]);

        $data = [
            'user_id' => $encargado->id,
            'role' => 'ENCARGADO'
        ];

        $response = $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/assign", $data);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertTrue($encargado->fresh()->hasRole('ENCARGADO'));
    }

    /** @test */
    public function test_unassign_removes_staff_role()
    {
        $coordinador = User::factory()->create();
        $coordinador->assignRole('COORDINADOR');

        ExpedienteAcademico::factory()->create([
            'user_id' => $coordinador->id,
            'ep_sede_id' => $this->epSede->id,
            'rol' => 'COORDINADOR',
            'estado' => 'ACTIVO'
        ]);

        $data = [
            'user_id' => $coordinador->id,
            'role' => 'COORDINADOR'
        ];

        $response = $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/unassign", $data);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        // Verificar que el rol fue removido
        $this->assertFalse($coordinador->fresh()->hasRole('COORDINADOR'));
    }

    /** @test */
    public function test_delegate_creates_interim_encargado()
    {
        $interino = User::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $interino->id,
            'ep_sede_id' => $this->epSede->id,
            'estado' => 'ACTIVO'
        ]);

        $data = [
            'user_id' => $interino->id,
            'vigente_hasta' => now()->addMonths(3)->toDateString()
        ];

        $response = $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/delegate", $data);

        $response->assertOk()
            ->assertJson(['ok' => true]);

        // Verificar que tiene rol ENCARGADO
        $this->assertTrue($interino->fresh()->hasRole('ENCARGADO'));

        // Verificar que el expediente tiene vigente_hasta
        $expediente = ExpedienteAcademico::where('user_id', $interino->id)
            ->where('ep_sede_id', $this->epSede->id)
            ->first();
        $this->assertNotNull($expediente->vigente_hasta);
    }

    /** @test */
    public function test_lookup_by_email_finds_user()
    {
        $user = User::factory()->create([
            'email' => 'test@universidad.edu'
        ]);

        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $this->epSede->id,
            'estado' => 'ACTIVO'
        ]);

        $response = $this->getJson("/api/ep-sedes/{$this->epSede->id}/staff/lookup?email=test@universidad.edu");

        $response->assertOk()
            ->assertJson([
                'user' => [
                    'id' => $user->id,
                    'email' => 'test@universidad.edu'
                ]
            ]);
    }

    /** @test */
    public function test_create_and_assign_creates_user_and_assigns_role()
    {
        $data = [
            'username' => 'nuevo.coordinador',
            'email' => 'nuevo@universidad.edu',
            'first_name' => 'Nuevo',
            'last_name' => 'Coordinador',
            'role' => 'COORDINADOR',
            'codigo_estudiante' => '2024999'
        ];

        $response = $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/create-and-assign", $data);

        $response->assertCreated()
            ->assertJson(['ok' => true]);

        // Verificar que el usuario fue creado
        $this->assertDatabaseHas('users', [
            'username' => 'nuevo.coordinador',
            'email' => 'nuevo@universidad.edu'
        ]);

        // Verificar que tiene el rol
        $user = User::where('username', 'nuevo.coordinador')->first();
        $this->assertTrue($user->hasRole('COORDINADOR'));

        // Verificar que tiene expediente
        $this->assertDatabaseHas('expedientes_academicos', [
            'user_id' => $user->id,
            'ep_sede_id' => $this->epSede->id,
            'estado' => 'ACTIVO'
        ]);
    }

    /** @test */
    public function test_endpoints_require_permissions()
    {
        // Usuario sin permisos
        $userSinPermisos = User::factory()->create();
        Sanctum::actingAs($userSinPermisos);

        $this->postJson("/api/ep-sedes/{$this->epSede->id}/staff/assign", [
            'user_id' => 1,
            'role' => 'COORDINADOR'
        ])->assertForbidden();
    }
}
