<?php
namespace Tests\Feature\Services\Reportes;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Services\Reportes\HorasPorPeriodoService;
use App\Models\EpSede;
use App\Models\PeriodoAcademico;
use App\Models\User;

class HorasPorPeriodoServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearDatosBasicos(): array
    {
        $epSede = EpSede::factory()->create();

        $u1 = User::factory()->create(['first_name'=>'Ana','last_name'=>'Zapata']);
        $u2 = User::factory()->create(['first_name'=>'Bruno','last_name'=>'Yupanqui']);

        DB::table('expedientes_academicos')->insert([
            ['id'=>1,'user_id'=>$u1->id,'ep_sede_id'=>$epSede->id,'estado'=>'ACTIVO','codigo_estudiante'=>'20201234'],
            ['id'=>2,'user_id'=>$u2->id,'ep_sede_id'=>$epSede->id,'estado'=>'ACTIVO','codigo_estudiante'=>'20205678'],
        ]);

        return [$epSede, $u1, $u2];
    }

    private function crearPeriodos(): array
    {
        $p2025_1 = PeriodoAcademico::create([
            'codigo'=>'2025-1','anio'=>2025,'ciclo'=>1,'estado'=>'CERRADO',
            'fecha_inicio'=>'2025-03-01','fecha_fin'=>'2025-07-31'
        ]);
        $p2025_2 = PeriodoAcademico::create([
            'codigo'=>'2025-2','anio'=>2025,'ciclo'=>2,'estado'=>'CERRADO',
            'fecha_inicio'=>'2025-08-01','fecha_fin'=>'2025-12-15'
        ]);
        $p2026_1 = PeriodoAcademico::create([
            'codigo'=>'2026-1','anio'=>2026,'ciclo'=>1,'estado'=>'EN_CURSO',
            'fecha_inicio'=>'2026-03-01','fecha_fin'=>'2026-07-31'
        ]);

        return [$p2025_1,$p2025_2,$p2026_1];
    }

    public function test_build_con_periodos_explicitos()
    {
        [$epSede] = $this->crearDatosBasicos();
        [$p2025_1,$p2025_2] = $this->crearPeriodos();

        DB::table('registro_horas')->insert([
            [
                'id'=>1,
                'expediente_id'=>1,
                'ep_sede_id'=>$epSede->id,
                'periodo_id'=>$p2025_1->id,
                'fecha'=>'2025-04-10',
                'minutos'=>120,
                'estado'=>'APROBADO',
                'actividad'=>'Sesión A',
                'vinculable_id'=>1,
                'vinculable_type'=>'App\\Models\\Proyecto',
            ],
            [
                'id'=>2,
                'expediente_id'=>1,
                'ep_sede_id'=>$epSede->id,
                'periodo_id'=>$p2025_2->id,
                'fecha'=>'2025-10-10',
                'minutos'=>90,
                'estado'=>'APROBADO',
                'actividad'=>'Sesión B',
                'vinculable_id'=>1,
                'vinculable_type'=>'App\\Models\\Proyecto',
            ],
            [
                'id'=>3,
                'expediente_id'=>2,
                'ep_sede_id'=>$epSede->id,
                'periodo_id'=>null,
                'fecha'=>'2024-12-15',
                'minutos'=>200,
                'estado'=>'APROBADO',
                'actividad'=>'Horas previas',
                'vinculable_id'=>1,
                'vinculable_type'=>'App\\Models\\Proyecto',
            ],
        ]);

        $svc = app(HorasPorPeriodoService::class);

        $res = $svc->build(
            $epSede->id,
            ['2025-1','2025-2'],
            null,
            'APROBADO',
            true,
            'h',
            'apellidos',
            'asc'
        );

        $this->assertSame('ANTES_DE_2025', $res['meta']['bucket_antes']);
        $this->assertSame(['2025-1','2025-2'], array_column($res['meta']['periodos'], 'codigo'));

        $this->assertCount(1, $res['data']);
        $ana = $res['data'][0];

        $this->assertEquals(2.00, $ana['buckets']['2025-1']);
        $this->assertEquals(1.50, $ana['buckets']['2025-2']);
        $this->assertEquals(0.00, $ana['buckets']['ANTES_DE_2025']);
        $this->assertEquals(3.50, $ana['total']);
    }

    public function test_build_ultimos_n_minutos_con_anteriores_visibles()
    {
        [$epSede] = $this->crearDatosBasicos();
        [, ,$p2026_1] = $this->crearPeriodos();

        DB::table('registro_horas')->insert([
            [
                'id'=>11,
                'expediente_id'=>1,
                'ep_sede_id'=>$epSede->id,
                'periodo_id'=>$p2026_1->id,
                'fecha'=>'2026-04-10',
                'minutos'=>30,
                'estado'=>'APROBADO',
                'actividad'=>'Sesión C',
                'vinculable_id'=>1,
                'vinculable_type'=>'App\\Models\\Proyecto',
            ],
            [
                'id'=>12,
                'expediente_id'=>2,
                'ep_sede_id'=>$epSede->id,
                'periodo_id'=>null,
                'fecha'=>'2024-01-10',
                'minutos'=>75,
                'estado'=>'APROBADO',
                'actividad'=>'Horas previas',
                'vinculable_id'=>1,
                'vinculable_type'=>'App\\Models\\Proyecto',
            ],
        ]);

        $svc = app(HorasPorPeriodoService::class);

        $res = $svc->build(
            $epSede->id,
            null,
            2,
            'APROBADO',
            false,
            'min',
            'total',
            'desc'
        );

        $this->assertSame(['2025-2','2026-1'], array_column($res['meta']['periodos'], 'codigo'));
        $this->assertCount(2, $res['data']);
        $this->assertSame('20205678', $res['data'][0]['codigo']);
        $this->assertSame('20201234', $res['data'][1]['codigo']);
    }
}
