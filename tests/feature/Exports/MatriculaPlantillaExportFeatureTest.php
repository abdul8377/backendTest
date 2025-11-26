<?php
// tests/Feature/Exports/MatriculaPlantillaExportFeatureTest.php

namespace Tests\Feature\Exports;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use App\Exports\MatriculaPlantillaExport;
use PHPUnit\Framework\Attributes\Test;

class MatriculaPlantillaExportFeatureTest extends TestCase
{
    #[Test]
    public function genera_workbook_con_tres_hojas_en_orden_y_catalogos_oculta()
    {
        Storage::fake('local');

        Excel::store(new MatriculaPlantillaExport(), 'matricula_template.xlsx', 'local');

        $path = Storage::disk('local')->path('matricula_template.xlsx');
        $xlsx = IOFactory::load($path);

        // 1) Hojas y orden
        $this->assertSame(3, $xlsx->getSheetCount());
        $this->assertSame('Plantilla',     $xlsx->getSheet(0)->getTitle());
        $this->assertSame('Instrucciones', $xlsx->getSheet(1)->getTitle());
        $this->assertSame('Catálogos',     $xlsx->getSheet(2)->getTitle());

        // 2) Catálogos oculta
        $catalogos = $xlsx->getSheetByName('Catálogos');
        $this->assertSame(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN,
            $catalogos->getSheetState()
        );

        // 3) Validaciones en Plantilla que referencian Catálogos
        $plantilla = $xlsx->getSheetByName('Plantilla');

        // A2 (Modo contrato) -> lista basada en Catálogos!A2:A5
        $valA2 = $plantilla->getCell('A2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valA2->getType());
        $this->assertStringContainsString("'Catálogos'!\$A\$2:\$A\$5", $valA2->getFormula1());

        // B2 (Modalidad estudio) -> Catálogos!B2:B4
        $valB2 = $plantilla->getCell('B2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valB2->getType());
        $this->assertStringContainsString("'Catálogos'!\$B\$2:\$B\$4", $valB2->getFormula1());

        // L2 (País) -> Catálogos!C2:C200
        $valL2 = $plantilla->getCell('L2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valL2->getType());
        $this->assertStringContainsString("'Catálogos'!\$C\$2:\$C\$200", $valL2->getFormula1());
    }
}
