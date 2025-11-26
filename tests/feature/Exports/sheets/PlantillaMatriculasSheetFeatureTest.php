<?php
// tests/Feature/Exports/PlantillaMatriculasSheetFeatureTest.php

namespace Tests\Feature\Exports\sheets;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Sheets\PlantillaMatriculasSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PHPUnit\Framework\Attributes\Test;

class PlantillaMatriculasSheetFeatureTest extends TestCase
{
    #[Test]
    public function genera_excel_con_estilos_validaciones_y_comentarios()
    {
        Storage::fake('local');

        Excel::store(new PlantillaMatriculasSheet(), 'plantilla_test.xlsx', 'local');

        $path = Storage::disk('local')->path('plantilla_test.xlsx');
        $xlsx = IOFactory::load($path);
        $ws   = $xlsx->getSheet(0);

        // Título de hoja
        $this->assertSame('Plantilla', $ws->getTitle());

        // Congelar encabezado: pane en A2 (fila 2)
        $pane = $ws->getFreezePane();
        $this->assertSame('A2', $pane);

        // Encabezado en negrita y centrado
        $bold = $ws->getStyle('A1:O1')->getFont()->getBold();
        $this->assertTrue($bold);

        $align = $ws->getStyle('A1:O1')->getAlignment()->getHorizontal();
        $this->assertSame(Alignment::HORIZONTAL_CENTER, $align);

        // Encabezados exactos en celdas
        $this->assertSame('Modo contrato', $ws->getCell('A1')->getValue());
        $this->assertSame('Modalidad estudio', $ws->getCell('B1')->getValue());
        $this->assertSame('Fecha de nacimiento', $ws->getCell('N1')->getValue());
        $this->assertSame('Fecha de matrícula', $ws->getCell('O1')->getValue());

        // Formato de fecha aplicado en columnas N y O
        $this->assertNotEmpty($ws->getStyle('N:N')->getNumberFormat()->getFormatCode());
        $this->assertNotEmpty($ws->getStyle('O:O')->getNumberFormat()->getFormatCode());

        // Comentarios existentes
        $this->assertNotNull($ws->getComment('O1')->getText());
        $this->assertNotNull($ws->getComment('L1')->getText());

        // Validación tipo LIST en A2 y B2 y L2
        $valA2 = $ws->getCell('A2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valA2->getType());
        $this->assertStringContainsString("'Catálogos'!\$A\$2:\$A\$5", $valA2->getFormula1());

        $valB2 = $ws->getCell('B2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valB2->getType());
        $this->assertStringContainsString("'Catálogos'!\$B\$2:\$B\$4", $valB2->getFormula1());

        $valL2 = $ws->getCell('L2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_LIST, $valL2->getType());
        $this->assertStringContainsString("'Catálogos'!\$C\$2:\$C\$200", $valL2->getFormula1());

        // Validación de fecha en N2 y O2
        $valN2 = $ws->getCell('N2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_DATE, $valN2->getType());

        $valO2 = $ws->getCell('O2')->getDataValidation();
        $this->assertSame(DataValidation::TYPE_DATE, $valO2->getType());
        $this->assertTrue($valO2->getAllowBlank()); // puede estar vacío
    }
}
