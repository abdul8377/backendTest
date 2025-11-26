<?php

namespace Tests\Feature\Services\Vm;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Vm\SesionBatchService;
use App\Models\{
    EpSede,
    PeriodoAcademico,
    VmProyecto,
    VmProceso,
    VmEvento,
    VmSesion
};
use Carbon\Carbon;

class SesionBatchServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_create_for_con_lista_de_fechas()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'list',
            'fechas' => [
                '2025-12-01',
                '2025-12-05',
                '2025-12-10',
            ],
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(3, $sesiones);

        $fechas = $sesiones->pluck('fecha')->map(fn($f) => $f->toDateString())->all();
        $this->assertContains('2025-12-01', $fechas);
        $this->assertContains('2025-12-05', $fechas);
        $this->assertContains('2025-12-10', $fechas);

        // Verificar horas
        foreach ($sesiones as $sesion) {
            $this->assertEquals('10:00:00', $sesion->hora_inicio);
            $this->assertEquals('12:00:00', $sesion->hora_fin);
        }
    }

    /** @test */
    public function test_create_for_con_rango_filtrando_dias_semana()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        // Del 1 al 7 de diciembre 2025:
        // 1 = Lunes, 2 = Martes, 3 = Miércoles, 4 = Jueves, 5 = Viernes, 6 = Sábado, 7 = Domingo
        $data = [
            'mode' => 'range',
            'fecha_inicio' => '2025-12-01',
            'fecha_fin' => '2025-12-07',
            'hora_inicio' => '09:00',
            'hora_fin' => '11:00',
            'dias_semana' => [1, 3, 5], // Lunes, Miércoles, Viernes
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        // Debe crear solo para Lunes (1), Miércoles (3), Viernes (5)
        $this->assertCount(3, $sesiones);

        $fechas = $sesiones->pluck('fecha')->map(fn($f) => $f->toDateString())->all();
        $this->assertContains('2025-12-01', $fechas); // Lunes
        $this->assertContains('2025-12-03', $fechas); // Miércoles
        $this->assertContains('2025-12-05', $fechas); // Viernes

        // No debe incluir otros días
        $this->assertNotContains('2025-12-02', $fechas); // Martes
        $this->assertNotContains('2025-12-04', $fechas); // Jueves
        $this->assertNotContains('2025-12-06', $fechas); // Sábado
        $this->assertNotContains('2025-12-07', $fechas); // Domingo
    }

    /** @test */
    public function test_create_for_con_dias_semana_en_texto()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'range',
            'fecha_inicio' => '2025-12-01',
            'fecha_fin' => '2025-12-07',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'dias_semana' => ['LU', 'MI', 'VI'], // Lunes, Miércoles, Viernes en español
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(3, $sesiones);

        $fechas = $sesiones->pluck('fecha')->map(fn($f) => $f->toDateString())->all();
        $this->assertContains('2025-12-01', $fechas); // Lunes
        $this->assertContains('2025-12-03', $fechas); // Miércoles
        $this->assertContains('2025-12-05', $fechas); // Viernes
    }

    /** @test */
    public function test_create_for_con_dias_semana_en_ingles()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'range',
            'fecha_inicio' => '2025-12-01',
            'fecha_fin' => '2025-12-07',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'dias_semana' => ['MON', 'WED', 'FRI'], // Lunes, Miércoles, Viernes en inglés
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(3, $sesiones);

        $fechas = $sesiones->pluck('fecha')->map(fn($f) => $f->toDateString())->all();
        $this->assertContains('2025-12-01', $fechas); // Lunes
        $this->assertContains('2025-12-03', $fechas); // Miércoles
        $this->assertContains('2025-12-05', $fechas); // Viernes
    }

    /** @test */
    public function test_create_for_normaliza_hora_sin_segundos()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'list',
            'fechas' => ['2025-12-01'],
            'hora_inicio' => '10:00', // Sin segundos
            'hora_fin' => '12:30',    // Sin segundos
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(1, $sesiones);
        $this->assertEquals('10:00:00', $sesiones[0]->hora_inicio);
        $this->assertEquals('12:30:00', $sesiones[0]->hora_fin);
    }

    /** @test */
    public function test_create_for_normaliza_hora_con_un_digito()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'list',
            'fechas' => ['2025-12-01'],
            'hora_inicio' => '8:00',  // Un dígito
            'hora_fin' => '9:30',     // Un dígito
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(1, $sesiones);
        $this->assertEquals('08:00:00', $sesiones[0]->hora_inicio);
        $this->assertEquals('09:30:00', $sesiones[0]->hora_fin);
    }

    /** @test */
    public function test_create_for_no_duplica_sesiones_existentes()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        // Crear una sesión existente
        VmSesion::factory()->create([
            'sessionable_type' => 'vm_proceso', // Usar alias del morph map
            'sessionable_id' => $proceso->id,
            'fecha' => '2025-12-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
            'estado' => 'PLANIFICADO',
        ]);

        $data = [
            'mode' => 'list',
            'fechas' => [
                '2025-12-01', // Ya existe
                '2025-12-02', // Nueva
            ],
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(2, $sesiones);

        // Verificar que solo hay 2 sesiones en total (no se duplicó)
        $totalSesiones = VmSesion::where('sessionable_type', 'vm_proceso')
            ->where('sessionable_id', $proceso->id)
            ->count();
        $this->assertEquals(2, $totalSesiones);
    }

    /** @test */
    public function test_create_for_funciona_con_evento()
    {
        // Arrange
        $periodo = PeriodoAcademico::factory()->create();
        $evento = VmEvento::factory()->create([
            'periodo_id' => $periodo->id,
        ]);

        $data = [
            'mode' => 'list',
            'fechas' => [
                '2025-12-15',
                '2025-12-16',
            ],
            'hora_inicio' => '18:00',
            'hora_fin' => '20:00',
        ];

        // Act
        $sesiones = SesionBatchService::createFor($evento, $data);

        // Assert
        $this->assertCount(2, $sesiones);

        $this->assertEquals('2025-12-15', $sesiones[0]->fecha->toDateString());
        $this->assertEquals('18:00:00', $sesiones[0]->hora_inicio);
        $this->assertEquals('20:00:00', $sesiones[0]->hora_fin);
        $this->assertEquals('vm_evento', $sesiones[0]->sessionable_type);
        $this->assertEquals($evento->id, $sesiones[0]->sessionable_id);
    }

    /** @test */
    public function test_create_for_con_rango_largo()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        // Crear sesiones para todo un mes, solo días laborables (L-V)
        $data = [
            'mode' => 'range',
            'fecha_inicio' => '2025-12-01',
            'fecha_fin' => '2025-12-31',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'dias_semana' => [1, 2, 3, 4, 5], // Lunes a Viernes
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        // Diciembre 2025 tiene 31 días
        // Aproximadamente 22-23 días laborables
        $this->assertGreaterThan(20, $sesiones->count());
        $this->assertLessThan(25, $sesiones->count());

        // Verificar que todas son días laborables
        foreach ($sesiones as $sesion) {
            $this->assertContains($sesion->fecha->dayOfWeek, [1, 2, 3, 4, 5]);
        }
    }

    /** @test */
    public function test_create_for_retorna_coleccion_vacia_si_no_hay_fechas()
    {
        // Arrange
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);

        $data = [
            'mode' => 'list',
            'fechas' => [],
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
        ];

        // Act
        $sesiones = SesionBatchService::createFor($proceso, $data);

        // Assert
        $this->assertCount(0, $sesiones);
    }
}
