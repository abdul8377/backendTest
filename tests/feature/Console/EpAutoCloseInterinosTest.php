<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\ExpedienteAcademico;
use App\Models\EpSedeStaffHistorial;
use App\Models\EpSede;
use Illuminate\Support\Carbon;

class EpAutoCloseInterinosTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_auto_close_interinos_cierra_expedientes_vencidos()
    {
        // Arrange
        $sede = EpSede::factory()->create();
        $user = User::factory()->create();

        // Crear roles necesarios
        \Spatie\Permission\Models\Role::create(['name' => 'ENCARGADO', 'guard_name' => 'web']);
        \Spatie\Permission\Models\Role::create(['name' => 'COORDINADOR', 'guard_name' => 'web']);

        // Crear expediente vencido ayer
        $expVencido = ExpedienteAcademico::create([
            'user_id' => $user->id,
            'ep_sede_id' => $sede->id,
            'rol' => 'ENCARGADO',
            'estado' => 'ACTIVO',
            'vigente_desde' => Carbon::yesterday()->subMonth(),
            'vigente_hasta' => Carbon::yesterday(), // Venció ayer
        ]);

        // Asignar rol al usuario (simulando que lo tiene)
        $user->assignRole('ENCARGADO');

        // Crear expediente vigente (no debe cerrarse)
        $expVigente = ExpedienteAcademico::create([
            'user_id' => User::factory()->create()->id,
            'ep_sede_id' => $sede->id,
            'rol' => 'COORDINADOR',
            'estado' => 'ACTIVO',
            'vigente_desde' => Carbon::now()->subMonth(),
            'vigente_hasta' => Carbon::tomorrow(), // Vence mañana
        ]);

        // Act
        $this->artisan('ep:staff:auto-close-interinatos')
            ->assertExitCode(0);

        // Assert

        // 1. Verificar expediente vencido
        $expVencido->refresh();
        $this->assertEquals('SUSPENDIDO', $expVencido->estado);

        // 2. Verificar historial creado
        $this->assertDatabaseHas('ep_sede_staff_historial', [
            'user_id' => $user->id,
            'role' => 'ENCARGADO',
            'evento' => 'AUTO_END',
            'motivo' => 'Vencimiento automático de interinato',
        ]);

        // 3. Verificar que se removió el rol (si el método removeRole funciona en el mock/test)
        // Nota: Esto depende de la implementación de Spatie/Permission en el modelo User.
        // Asumimos que funciona.
        $this->assertFalse($user->fresh()->hasRole('ENCARGADO'));

        // 4. Verificar expediente vigente intacto
        $expVigente->refresh();
        $this->assertEquals('ACTIVO', $expVigente->estado);
    }
}
