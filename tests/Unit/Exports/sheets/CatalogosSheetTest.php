<?php
// tests/Unit/Exports/CatalogosSheetTest.php

namespace Tests\Unit\Exports\sheets;

use PHPUnit\Framework\TestCase;
use App\Exports\Sheets\CatalogosSheet;
use Maatwebsite\Excel\Events\AfterSheet;

final class CatalogosSheetTest extends TestCase
{
    public function test_title_es_catalogos()
    {
        $sheet = new CatalogosSheet();
        $this->assertSame('Catálogos', $sheet->title());
    }

    public function test_array_tiene_cabecera_y_filas_basicas()
    {
        $sheet = new CatalogosSheet();
        $data  = $sheet->array();

        // Cabecera exacta
        $this->assertSame(
            ['modo_contrato','modalidad_estudio','pais_nombre','pais_iso2'],
            $data[0]
        );

        // Algunas filas esperadas (ejemplo: Perú y Chile)
        $this->assertContains(['Regular','Presencial','Perú','PE'], $data);
        $this->assertContains(['Convenio','Semipresencial','Chile','CL'], $data);
    }

    public function test_register_events_incluye_after_sheet_callable()
    {
        $sheet   = new CatalogosSheet();
        $events  = $sheet->registerEvents();

        $this->assertArrayHasKey(AfterSheet::class, $events);
        $this->assertIsCallable($events[AfterSheet::class]);
    }
}
