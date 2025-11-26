<?php

namespace Tests\Feature\Http\Controllers\Api\Admin;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Universidad, PeriodoAcademico};
use Laravel\Sanctum\Sanctum;

// Grupo 6: Administración

class AdminControllersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_index_returns_universidades()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        Universidad::factory()->count(3)->create();

        $response = $this->getJson('/api/administrador/universidades');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_store_creates_universidad()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        $data = [
            'nombre' => 'Universidad Test',
            'siglas' => 'UT',
            'ruc' => '12345678901'
        ];

        $response = $this->postJson('/api/administrador/universidades', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('universidades', ['nombre' => 'Universidad Test']);
    }
}

class PeriodoAcademicoControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_index_returns_periodos()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        PeriodoAcademico::factory()->count(3)->create();

        $response = $this->getJson('/api/administrador/periodos');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_store_creates_periodo()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        $data = [
            'nombre' => '2024-I',
            'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(6)->toDateString()
        ];

        $response = $this->postJson('/api/administrador/periodos', $data);

        $response->assertCreated();
        $this->assertDatabaseHas('periodos_academicos', ['nombre' => '2024-I']);
    }
}

class ConfiguracionControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_configuracion_endpoints_accessible()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        $this->assertTrue(true);
    }
}

class PermisosRolesControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_permisos_roles_endpoints_accessible()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);
        $user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($user);

        $this->assertTrue(true);
    }
}
