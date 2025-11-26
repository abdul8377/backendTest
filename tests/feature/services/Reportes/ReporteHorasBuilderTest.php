<?php

namespace Tests\Feature\Services\Reportes;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;

use App\Services\Reportes\ReporteHorasBuilder;
use App\Models\PeriodoAcademico;
use App\Models\RegistroHora;
use App\Models\EpSede;
use App\Models\ExpedienteAcademico;
use Carbon\Carbon;

class ReporteHorasBuilderTest extends TestCase
{
    use RefreshDatabase;

    private ReporteHorasBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ReporteHorasBuilder();
    }

    /**
     * Normaliza el formato del bloque "historial" para soportar
     * responses con y sin wrapping de JsonResource.
     */
    private function extractHistorialItems(array $json): array
    {
        // historial puede estar en $json['data']['historial'] (según tu service)
        $h = $json['data']['historial'] ?? [];

        // Caso 1: viene paginado envuelto -> ['data' => [...]]
        if (is_array($h) && array_key_exists('data', $h) && is_array($h['data'])) {
            return $h['data'];
        }

        // Caso 2: viene como lista directa (sin 'data')
        return is_array($h) ? $h : [];
    }

    #[Test]
    public function construye_reporte_por_defecto_con_estado_aprobado_y_agrupaciones(): void
    {
        $p1 = PeriodoAcademico::factory()->create([
            'anio'   => 2025,
            'ciclo'  => 1,
            'codigo' => '2025-1',
        ]);

        $p2 = PeriodoAcademico::factory()->create([
            'anio'   => 2025,
            'ciclo'  => 2,
            'codigo' => '2025-2',
        ]);

        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'ep_sede_id' => $epSede->id,
            'estado'     => 'ACTIVO',
        ]);

        // Aprobados en p1
        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p1->id,
            'fecha'           => '2025-05-10',
            'minutos'         => 60,
            'estado'          => 'APROBADO',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Charla colegios',
        ]);

        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p1->id,
            'fecha'           => '2025-05-15',
            'minutos'         => 30,
            'estado'          => 'APROBADO',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Planificación',
        ]);

        // Pendiente en p2 (no debe contarse por defecto)
        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p2->id,
            'fecha'           => '2025-06-01',
            'minutos'         => 45,
            'estado'          => 'PENDIENTE',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Voluntariado',
        ]);

        $req = Request::create('/reporte', 'GET', ['per_page' => 10]);

        $resp = $this->builder->build($req, $expediente->id);
        $this->assertSame(200, $resp->getStatusCode());
        $json = $resp->getData(true);

        // Resumen total (solo aprobados)
        $this->assertSame(90, $json['data']['resumen']['total_minutos']);
        $this->assertSame(1.5, $json['data']['resumen']['total_horas']);

        // Agrupación por periodo (solo p1)
        $porPeriodo = collect($json['data']['resumen']['por_periodo']);
        $this->assertCount(1, $porPeriodo);
        $this->assertSame($p1->id, $porPeriodo[0]['periodo_id']);
        $this->assertSame('2025-1', $porPeriodo[0]['codigo']);
        $this->assertSame(90, $porPeriodo[0]['minutos']);
        $this->assertSame(1.5, $porPeriodo[0]['horas']);

        // Agrupación por vínculo (EpSede)
        $porVinculo = collect($json['data']['resumen']['por_vinculo']);
        $this->assertCount(1, $porVinculo);
        $this->assertSame(\App\Models\EpSede::class, $porVinculo[0]['tipo']);
        $this->assertSame($epSede->id, $porVinculo[0]['id']);
        $this->assertNull($porVinculo[0]['titulo']); // EpSede no tiene titulo/nombre
        $this->assertSame(90, $porVinculo[0]['minutos']);
        $this->assertSame(1.5, $porVinculo[0]['horas']);

        // Meta de paginación
        $this->assertSame(1, $json['meta']['current_page']);
        $this->assertSame(10, $json['meta']['per_page']);
        $this->assertSame(2, $json['meta']['total']);
        $this->assertSame(1, $json['meta']['last_page']);

        // Historial (normalizado)
        $items = $this->extractHistorialItems($json);
        $this->assertCount(2, $items);
    }

    #[Test]
    public function aplica_filtros_periodo_fecha_texto_estado_tipo_y_vinculable_id(): void
    {
        $p1 = PeriodoAcademico::factory()->create(['codigo' => '2025-1']);
        $p2 = PeriodoAcademico::factory()->create(['codigo' => '2025-2']);

        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'ep_sede_id' => $epSede->id,
            'estado'     => 'ACTIVO',
        ]);

        // Coincide con todos los filtros
        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p1->id,
            'fecha'           => '2025-03-05',
            'minutos'         => 40,
            'estado'          => 'APROBADO',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Feria',
        ]);

        // No debe entrar por periodo/fecha/texto
        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p2->id,
            'fecha'           => '2025-07-01',
            'minutos'         => 25,
            'estado'          => 'APROBADO',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Taller',
        ]);

        // No debe entrar por estado (PENDIENTE) aunque cumpla otras
        RegistroHora::factory()->create([
            'expediente_id'   => $expediente->id,
            'ep_sede_id'      => $epSede->id,
            'periodo_id'      => $p1->id,
            'fecha'           => '2025-03-08',
            'minutos'         => 15,
            'estado'          => 'PENDIENTE',
            'vinculable_type' => \App\Models\EpSede::class,
            'vinculable_id'   => $epSede->id,
            'actividad'       => 'Plan',
        ]);

        $req = Request::create('/reporte', 'GET', [
            'periodo_id'    => $p1->id,
            'desde'         => '2025-03-01',
            'hasta'         => '2025-03-31',
            'q'             => 'Feria',
            'estado'        => '*',
            'tipo'          => \App\Models\EpSede::class,
            'vinculable_id' => $epSede->id,
            'per_page'      => 5,
        ]);

        $resp = $this->builder->build($req, $expediente->id);
        $this->assertSame(200, $resp->getStatusCode());
        $json = $resp->getData(true);

        $this->assertSame(40, $json['data']['resumen']['total_minutos']);
        $this->assertSame(1, $json['meta']['total']);

        $items = $this->extractHistorialItems($json);
        $this->assertCount(1, $items);

        $porPeriodo = collect($json['data']['resumen']['por_periodo']);
        $this->assertCount(1, $porPeriodo);
        $this->assertSame($p1->id, $porPeriodo[0]['periodo_id']);
        $this->assertSame('2025-1', $porPeriodo[0]['codigo']);

        $porVinculo = collect($json['data']['resumen']['por_vinculo']);
        $this->assertCount(1, $porVinculo);
        $this->assertSame(\App\Models\EpSede::class, $porVinculo[0]['tipo']);
        $this->assertSame($epSede->id, $porVinculo[0]['id']);
        $this->assertSame(40, $porVinculo[0]['minutos']);
    }

    #[Test]
    public function paginacion_respeta_limites_y_ordenamiento(): void
    {
        $p = PeriodoAcademico::factory()->create(['codigo' => '2025-1']);

        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'ep_sede_id' => $epSede->id,
            'estado'     => 'ACTIVO',
        ]);

        foreach (range(1, 25) as $i) {
            RegistroHora::factory()->create([
                'expediente_id'   => $expediente->id,
                'ep_sede_id'      => $epSede->id,
                'periodo_id'      => $p->id,
                'fecha'           => Carbon::parse('2025-04-01')->addDays($i)->toDateString(),
                'minutos'         => 10,
                'estado'          => 'APROBADO',
                'vinculable_type' => \App\Models\EpSede::class,
                'vinculable_id'   => $epSede->id,
                'actividad'       => "Act $i",
            ]);
        }

        $req = Request::create('/reporte', 'GET', ['per_page' => 7]);

        $resp = $this->builder->build($req, $expediente->id);
        $this->assertSame(200, $resp->getStatusCode());
        $json = $resp->getData(true);

        $this->assertSame(7, $json['meta']['per_page']);
        $this->assertSame(25, $json['meta']['total']);
        $this->assertSame(4, $json['meta']['last_page']);

        $items = $this->extractHistorialItems($json);
        $this->assertCount(7, $items);

        $first = $items[0];
        $last  = $items[count($items) - 1];
        $this->assertTrue($first['fecha'] >= $last['fecha']);
    }
}
