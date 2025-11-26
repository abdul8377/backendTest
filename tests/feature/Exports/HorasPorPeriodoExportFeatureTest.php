<?php
// tests/Feature/Exports/HorasPorPeriodoExportFeatureTest.php

namespace Tests\Feature\Exports;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use App\Exports\HorasPorPeriodoExport;
use PHPUnit\Framework\Attributes\Test;

class HorasPorPeriodoExportFeatureTest extends TestCase
{
    #[Test]
    public function genera_xlsx_con_encabezados_dinamicos_y_negrita()
    {
        Storage::fake('local');

        $meta = [
            'bucket_antes' => 'ANT',
            'periodos' => [
                ['codigo' => '2024-1'],
                ['codigo' => '2024-2'],
                ['codigo' => '2025-1'],
            ],
        ];
        $rows = [
            [
                'ep' => 'EP-IS',
                'codigo' => 'A001',
                'apellidos_nombres' => 'APELLIDOS NOMBRES',
                'buckets' => ['ANT'=>5,'2024-1'=>10,'2024-2'=>7,'2025-1'=>0],
                'total' => 22,
            ],
        ];

        Excel::store(new HorasPorPeriodoExport($meta, $rows), 'horas_periodo.xlsx', 'local');

        $path = Storage::disk('local')->path('horas_periodo.xlsx');
        $xlsx = IOFactory::load($path);
        $ws = $xlsx->getSheet(0); // primera hoja

        // Encabezados esperados en la primera fila
        $expected = ['EP','CODIGO','AP. Y NOMBRES','ANT','2024-1','2024-2','2025-1','TOTAL'];

        foreach ($expected as $idx => $text) {
            // Columnas A,B,C... -> índice 0,1,2...
            $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($idx+1) . '1';
            $this->assertSame($text, $ws->getCell($cell)->getValue());
        }

        // Cabecera en negrita (styles() aplica A1:Z1)
        $this->assertTrue($ws->getStyle('A1:Z1')->getFont()->getBold());

        // Verifica el primer dato mapeado (fila 2)
        $this->assertSame('EP-IS', $ws->getCell('A2')->getValue());
        $this->assertSame('A001',  $ws->getCell('B2')->getValue());
        $this->assertSame('APELLIDOS NOMBRES', $ws->getCell('C2')->getValue());
        $this->assertEquals(5,  $ws->getCell('D2')->getValue()); // ANT
        $this->assertEquals(10, $ws->getCell('E2')->getValue()); // 2024-1
        $this->assertEquals(7,  $ws->getCell('F2')->getValue()); // 2024-2
        $this->assertEquals(0,  $ws->getCell('G2')->getValue()); // 2025-1
        $this->assertEquals(22, $ws->getCell('H2')->getValue()); // TOTAL
    }
}
