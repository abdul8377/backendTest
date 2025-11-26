<?php

namespace Tests\Feature\Http\Controllers\Api\Reportes;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// Grupo 5: Reportes

class ReportesControllersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_reporte_vm_endpoints_accessible()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'reporte.vm.read', 'guard_name' => 'web']);
        $user->givePermissionTo('reporte.vm.read');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reportes/vm/resumen');

        $response->assertOk();
    }
}

class ReporteMatriculaControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_reporte_matricula_endpoints_accessible()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'reporte.matricula.read', 'guard_name' => 'web']);
        $user->givePermissionTo('reporte.matricula.read');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reportes/matriculas/resumen');

        $response->assertOk();
    }
}

class ReporteGeneralControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_reporte_general_endpoints_accessible()
    {
        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::create(['name' => 'reporte.general.read', 'guard_name' => 'web']);
        $user->givePermissionTo('reporte.general.read');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/reportes/general/dashboard');

        $response->assertOk();
    }
}
