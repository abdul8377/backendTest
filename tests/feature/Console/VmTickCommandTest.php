<?php

namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\VmSesion;
use App\Models\VmProceso;
use App\Models\VmProyecto;
use App\Models\EpSede;
use App\Models\PeriodoAcademico;
use Illuminate\Support\Carbon;

class VmTickCommandTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_vm_tick_actualiza_estados_correctamente()
    {
        // Congela el tiempo
        $now = Carbon::parse('2025-03-10 10:00:00'); // hora de referencia fija
        Carbon::setTestNow($now);

        $epSede   = EpSede::factory()->create();
        $periodo  = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso  = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        // 1) Debe pasar a EN_CURSO
        $sesionStart = VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id'   => $proceso->id,
            'fecha'            => $now->toDateString(),              // 2025-03-10
            'hora_inicio'      => $now->copy()->subMinutes(10)->format('H:i:s'), // 09:50
            'hora_fin'         => $now->copy()->addMinutes(50)->format('H:i:s'), // 10:50
            'estado'           => 'PLANIFICADO',
        ]);

        // 2) Debe pasar a CERRADO (fin ya pasó)
        $sesionClose = VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id'   => $proceso->id,
            'fecha'            => $now->toDateString(),
            'hora_inicio'      => $now->copy()->subHours(2)->format('H:i:s'), // 08:00
            'hora_fin'         => $now->copy()->subHour()->format('H:i:s'),   // 09:00
            'estado'           => 'EN_CURSO', // o PLANIFICADO, ambas deberían cerrar
        ]);

        // 3) Futura (no cambia)
        $sesionFuture = VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id'   => $proceso->id,
            'fecha'            => $now->copy()->addDay()->toDateString(), // 2025-03-11
            'hora_inicio'      => '10:00:00',
            'hora_fin'         => '12:00:00',
            'estado'           => 'PLANIFICADO',
        ]);

        $this->artisan('vm:tick')->assertExitCode(0);

        $this->assertEquals('EN_CURSO',   $sesionStart->fresh()->estado);
        $this->assertEquals('CERRADO',    $sesionClose->fresh()->estado);
        $this->assertEquals('PLANIFICADO',$sesionFuture->fresh()->estado);
    }
}
