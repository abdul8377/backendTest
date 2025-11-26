<?php

namespace Tests\Feature\Services\Vm;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Vm\EstadoService;
use App\Models\{
    EpSede,
    PeriodoAcademico,
    VmProyecto,
    VmProceso,
    VmEvento,
    VmSesion
};
use Carbon\Carbon;

class EstadoServiceTest extends TestCase
{
    use RefreshDatabase;

    private EstadoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EstadoService::class);
    }

    /** @test */
    public function test_recalc_proceso_sin_sesiones_queda_planificado()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('PLANIFICADO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proceso_con_sesiones_futuras_queda_planificado()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Crear sesiones futuras
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->addDays(10)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('PLANIFICADO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proceso_con_sesiones_pasadas_queda_cerrado()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Crear sesiones pasadas
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->subDays(10)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'CERRADO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('CERRADO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proceso_con_sesiones_mixtas_queda_en_curso()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Crear sesiones pasadas
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        // Crear sesiones futuras
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('EN_CURSO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proceso_con_sesion_en_curso_queda_en_curso()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Crear una sesión EN_CURSO
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => now()->subHour()->format('H:i:s'),
            'hora_fin' => now()->addHour()->format('H:i:s'),
            'estado' => 'EN_CURSO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('EN_CURSO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proceso_cancelado_no_cambia()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'CANCELADO',
        ]);

        // Crear sesiones
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('CANCELADO', $proceso->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proyecto_sin_sesiones_queda_planificado()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proyecto);

        // Assert
        $this->assertEquals('PLANIFICADO', $proyecto->fresh()->estado);
    }

    /** @test */
    public function test_recalc_proyecto_con_procesos_y_sesiones()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        $proceso1 = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $proceso2 = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        // Sesiones pasadas en proceso1
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso1->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        // Sesiones futuras en proceso2
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso2->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($proyecto);

        // Assert - Debe estar EN_CURSO porque tiene sesiones pasadas y futuras
        $this->assertEquals('EN_CURSO', $proyecto->fresh()->estado);
    }

    /** @test */
    public function test_recalc_evento_sin_sesiones_queda_planificado()
    {
        // Arrange
        $periodo = PeriodoAcademico::factory()->create();
        $evento = VmEvento::factory()->create([
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($evento);

        // Assert
        $this->assertEquals('PLANIFICADO', $evento->fresh()->estado);
    }

    /** @test */
    public function test_recalc_evento_con_sesiones_futuras_queda_planificado()
    {
        // Arrange
        $periodo = PeriodoAcademico::factory()->create();
        $evento = VmEvento::factory()->create([
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        VmSesion::factory()->create([
            'sessionable_type' => 'vm_evento',
            'sessionable_id' => $evento->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($evento);

        // Assert
        $this->assertEquals('PLANIFICADO', $evento->fresh()->estado);
    }

    /** @test */
    public function test_recalc_evento_con_sesiones_pasadas_queda_cerrado()
    {
        // Arrange
        $periodo = PeriodoAcademico::factory()->create();
        $evento = VmEvento::factory()->create([
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        VmSesion::factory()->create([
            'sessionable_type' => 'vm_evento',
            'sessionable_id' => $evento->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        // Act
        $this->service->recalcOwner($evento);

        // Assert
        $this->assertEquals('CERRADO', $evento->fresh()->estado);
    }

    /** @test */
    public function test_recalc_evento_con_sesiones_mixtas_queda_en_curso()
    {
        // Arrange
        $periodo = PeriodoAcademico::factory()->create();
        $evento = VmEvento::factory()->create([
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Sesión pasada
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_evento',
            'sessionable_id' => $evento->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        // Sesión futura
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_evento',
            'sessionable_id' => $evento->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act
        $this->service->recalcOwner($evento);

        // Assert
        $this->assertEquals('EN_CURSO', $evento->fresh()->estado);
    }

    /** @test */
    public function test_recalc_owner_maneja_null_gracefully()
    {
        // Act & Assert - No debe lanzar excepción
        $this->service->recalcOwner(null);
        $this->assertTrue(true);
    }

    /** @test */
    public function test_recalc_proceso_actualiza_proyecto_padre()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
            'estado' => 'PLANIFICADO',
        ]);
        $proceso = VmProceso::factory()->create([
            'proyecto_id' => $proyecto->id,
            'estado' => 'PLANIFICADO',
        ]);

        // Crear sesiones pasadas y futuras
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->subDays(5)->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'CERRADO',
        ]);

        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso',
            'sessionable_id' => $proceso->id,
            'fecha' => now()->addDays(5)->toDateString(),
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        // Act - Recalcular proceso (debe actualizar también el proyecto)
        $this->service->recalcOwner($proceso);

        // Assert
        $this->assertEquals('EN_CURSO', $proceso->fresh()->estado);
        $this->assertEquals('EN_CURSO', $proyecto->fresh()->estado);
    }
}
