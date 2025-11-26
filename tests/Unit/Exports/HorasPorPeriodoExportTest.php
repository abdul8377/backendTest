<?php
// tests/Unit/Exports/HorasPorPeriodoExportTest.php

namespace Tests\Unit\Exports;

use PHPUnit\Framework\TestCase;
use App\Exports\HorasPorPeriodoExport;

final class HorasPorPeriodoExportTest extends TestCase
{
    public function test_headings_con_bucket_antes()
    {
        $meta = [
            'escuela_profesional' => 'Ingeniería',
            'bucket_antes' => 'ANT',
            'periodos' => [
                ['codigo' => '2024-1'],
                ['codigo' => '2024-2'],
            ],
        ];
        $rows = [];

        $export = new HorasPorPeriodoExport($meta, $rows);
        $this->assertSame(
            ['EP','CODIGO','AP. Y NOMBRES','ANT','2024-1','2024-2','TOTAL'],
            $export->headings()
        );
    }

    public function test_headings_sin_bucket_antes()
    {
        $meta = [
            'escuela_profesional' => 'Ingeniería',
            'bucket_antes' => null,
            'periodos' => [
                ['codigo' => '2025-1'],
            ],
        ];
        $rows = [];

        $export = new HorasPorPeriodoExport($meta, $rows);
        $this->assertSame(
            ['EP','CODIGO','AP. Y NOMBRES','2025-1','TOTAL'],
            $export->headings()
        );
    }

    public function test_map_con_bucket_antes()
    {
        $meta = [
            'bucket_antes' => 'ANT',
            'periodos' => [
                ['codigo' => '2024-1'],
                ['codigo' => '2024-2'],
            ],
        ];
        $rows = [];
        $export = new HorasPorPeriodoExport($meta, $rows);

        $row = [
            'ep' => 'EP-IS',
            'codigo' => 'A001',
            'apellidos_nombres' => 'APELLIDOS NOMBRES',
            'buckets' => [
                'ANT' => 5,
                '2024-1' => 10,
                '2024-2' => 7,
            ],
            'total' => 22,
        ];

        $this->assertSame(
            ['EP-IS','A001','APELLIDOS NOMBRES',5,10,7,22],
            $export->map($row)
        );
    }

    public function test_map_sin_bucket_antes_y_valores_faltantes_en_buckets()
    {
        $meta = [
            'bucket_antes' => null,
            'periodos' => [
                ['codigo' => '2025-1'],
                ['codigo' => '2025-2'],
            ],
        ];
        $rows = [];
        $export = new HorasPorPeriodoExport($meta, $rows);

        $row = [
            'ep' => 'EP-ADM',
            'codigo' => 'B002',
            'apellidos_nombres' => 'NOMBRES APELLIDOS',
            'buckets' => [
                // falta '2025-2' -> debe caer 0
                '2025-1' => 3,
            ],
            'total' => 3,
        ];

        $this->assertSame(
            ['EP-ADM','B002','NOMBRES APELLIDOS',3,0,3],
            $export->map($row)
        );
    }

    public function test_array_retorna_las_rows_sin_encabezados()
    {
        $meta = ['bucket_antes' => null, 'periodos' => []];
        $rows = [
            ['ep'=>'EP-1','codigo'=>'X','apellidos_nombres'=>'AAA','buckets'=>[],'total'=>0],
            ['ep'=>'EP-2','codigo'=>'Y','apellidos_nombres'=>'BBB','buckets'=>[],'total'=>5],
        ];
        $export = new HorasPorPeriodoExport($meta, $rows);

        $this->assertSame($rows, $export->array());
    }
}
