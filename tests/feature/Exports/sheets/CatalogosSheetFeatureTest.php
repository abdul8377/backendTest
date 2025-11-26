<?php
// tests/Feature/Exports/CatalogosSheetFeatureTest.php

namespace Tests\Feature\Exports\sheets;

use Tests\TestCase;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Sheets\CatalogosSheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Test;

class CatalogosSheetFeatureTest extends TestCase
{
    #[Test]
    public function genera_excel_con_titulo_hoja_negrita_y_hoja_oculta()
    {
        Storage::fake('local');

        // Guarda el archivo real
        Excel::store(new CatalogosSheet(), 'catalogos_test.xlsx', 'local');

        // Carga con PhpSpreadsheet para inspeccionar
        $path = Storage::disk('local')->path('catalogos_test.xlsx');
        $xlsx = IOFactory::load($path);
        $ws   = $xlsx->getSheet(0);

        // Título de hoja
        $this->assertSame('Catálogos', $ws->getTitle());

        // Cabecera en negrita A1:D1
        $bold = $ws->getStyle('A1:D1')->getFont()->getBold();
        $this->assertTrue($bold);

        // Hoja oculta
        $this->assertSame(
            \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::SHEETSTATE_HIDDEN,
            $ws->getSheetState()
        );

        // Primera fila (cabecera) exacta
        $this->assertSame('modo_contrato',      $ws->getCell('A1')->getValue());
        $this->assertSame('modalidad_estudio',  $ws->getCell('B1')->getValue());
        $this->assertSame('pais_nombre',        $ws->getCell('C1')->getValue());
        $this->assertSame('pais_iso2',          $ws->getCell('D1')->getValue());

        // Una fila de datos clave (Perú→PE)
        $this->assertSame('Perú', $ws->getCell('C2')->getValue());
        $this->assertSame('PE',   $ws->getCell('D2')->getValue());
    }
}
