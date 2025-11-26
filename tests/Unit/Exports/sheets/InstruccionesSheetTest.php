<?php
// tests/Unit/Exports/InstruccionesSheetTest.php

namespace Tests\Unit\Exports\sheets;

use PHPUnit\Framework\TestCase;
use App\Exports\Sheets\InstruccionesSheet;

final class InstruccionesSheetTest extends TestCase
{
    public function test_title_es_instrucciones()
    {
        $sheet = new InstruccionesSheet();
        $this->assertSame('Instrucciones', $sheet->title());
    }

    public function test_array_tiene_recomendaciones_y_lineas_clave()
    {
        $sheet = new InstruccionesSheet();
        $data  = $sheet->array();

        $this->assertNotEmpty($data);
        $this->assertSame(['Recomendaciones para completar la plantilla'], $data[0]);

        // Verifica que existe la línea 1) y 2)
        $this->assertContains(['1) Los encabezados deben mantenerse tal cual aparecen en la hoja "Plantilla".'], $data);
        $this->assertContains(['2) "Fecha de matrícula":'], $data);
        // y una de las sub-líneas
        $this->assertContains(['   - Si el estudiante NO se matriculó este período, deja la celda VACÍA.'], $data);
    }
}
