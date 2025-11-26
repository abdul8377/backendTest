<?php

namespace Tests\Unit\Services\Auth;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

use App\Services\Auth\EpScopeService;
use App\Models\User;
use App\Models\ExpedienteAcademico;
use App\Models\EpSede;
use App\Models\EscuelaProfesional;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use PHPUnit\Framework\Attributes\Test;

class EpScopeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Asegura que Spatie no use permisos cacheados entre pruebas
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    #[Test]
    public function user_con_permiso_y_expediente_activo_gestiona_ep_sede(): void
    {
        // Permiso requerido
        Permission::create(['name' => EpScopeService::PERM_MANAGE_EP_SEDE]);

        // Usuario con permiso
        $user = User::factory()->create();
        $user->givePermissionTo(EpScopeService::PERM_MANAGE_EP_SEDE);

        // EP-Sede y expediente activo del usuario
        $epSede = EpSede::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertTrue(
            EpScopeService::userManagesEpSede($user->id, $epSede->id)
        );
    }

    #[Test]
    public function user_sin_permiso_no_gestiona_ep_sede(): void
    {
        $user = User::factory()->create();
        $epSede = EpSede::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertFalse(
            EpScopeService::userManagesEpSede($user->id, $epSede->id)
        );
    }

    #[Test]
    public function user_con_permiso_gestiona_sede(): void
    {
        Permission::create(['name' => EpScopeService::PERM_MANAGE_SEDE]);

        $user = User::factory()->create();
        $user->givePermissionTo(EpScopeService::PERM_MANAGE_SEDE);

        // EP-Sede perteneciente a una sede
        $sede = \App\Models\Sede::factory()->create();
        $epSede = EpSede::factory()->create(['sede_id' => $sede->id]);

        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertTrue(
            EpScopeService::userManagesSede($user->id, $sede->id)
        );
    }

    #[Test]
    public function user_con_permiso_gestiona_facultad(): void
    {
        Permission::create(['name' => EpScopeService::PERM_MANAGE_FACULTAD]);

        $user = User::factory()->create();
        $user->givePermissionTo(EpScopeService::PERM_MANAGE_FACULTAD);

        // Escuela perteneciente a una facultad
        $facultad = \App\Models\Facultad::factory()->create();
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);

        // EP-Sede asociada a esa escuela
        $epSede = EpSede::factory()->for($escuela)->create();

        // Expediente activo del usuario en esa EP-Sede
        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertTrue(
            EpScopeService::userManagesFacultad($user->id, $facultad->id)
        );
    }

    #[Test]
    public function devuelve_ep_sedes_unicas_que_el_usuario_gestiona(): void
    {
        Permission::create(['name' => EpScopeService::PERM_MANAGE_EP_SEDE]);

        $user = User::factory()->create();
        $user->givePermissionTo(EpScopeService::PERM_MANAGE_EP_SEDE);

        $ep1 = EpSede::factory()->create();
        $ep2 = EpSede::factory()->create();

        // Dos expedientes activos en EP-Sedes distintas
        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $ep1->id,
            'estado' => 'ACTIVO',
        ]);
        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $ep2->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertEqualsCanonicalizing(
            [$ep1->id, $ep2->id],
            EpScopeService::epSedesIdsManagedBy($user->id)
        );
    }

    #[Test]
    public function devuelve_ultimo_expediente_activo_si_tiene_permiso(): void
    {
        Permission::create(['name' => EpScopeService::PERM_VIEW_EXPEDIENTE]);

        $user = User::factory()->create();
        $user->givePermissionTo(EpScopeService::PERM_VIEW_EXPEDIENTE);

        // Crea dos expedientes activos; el último debe ser devuelto
        $exp1 = ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ACTIVO',
        ]);
        $exp2 = ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertEquals(
            $exp2->id,
            EpScopeService::expedienteId($user->id)
        );
    }

    #[Test]
    public function devuelve_ultimo_ep_sede_del_usuario(): void
    {
        $user = User::factory()->create();

        $ep1 = EpSede::factory()->create();
        $ep2 = EpSede::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $ep1->id,
            'estado' => 'ACTIVO',
        ]);
        $exp2 = ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $ep2->id,
            'estado' => 'ACTIVO',
        ]);

        $this->assertEquals(
            $ep2->id,
            EpScopeService::epSedeIdForUser($user->id)
        );
    }
}
