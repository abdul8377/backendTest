<?php
// tests/Unit/Exports/PlantillaMatriculasSheetTest.php

namespace Tests\Unit\Exports\sheets;

use PHPUnit\Framework\TestCase;
use App\Exports\Sheets\PlantillaMatriculasSheet;

final class PlantillaMatriculasSheetTest extends TestCase
{
    public function test_headings_exactos()
    {
        $sheet = new PlantillaMatriculasSheet();
        $expected = [
            'Modo contrato','Modalidad estudio','Ciclo','Grupo','Código estudiante',
            'Estudiante','Documento','Correo','Usuario','Correo Institucional',
            'Celular','Pais','Religión','Fecha de nacimiento','Fecha de matrícula',
        ];
        $this->assertSame($expected, $sheet->headings());
    }

    public function test_title_es_plantilla()
    {
        $sheet = new PlantillaMatriculasSheet();
        $this->assertSame('Plantilla', $sheet->title());
    }

    public function test_array_tiene_fila_ejemplo()
    {
        $sheet = new PlantillaMatriculasSheet();
        $rows  = $sheet->array();
        $this->assertCount(1, $rows);

        $row = $rows[0];
        $this->assertSame('Regular', $row[0]);      // Modo contrato
        $this->assertSame('Presencial', $row[1]);   // Modalidad
        $this->assertSame('Perú', $row[11]);        // País
        $this->assertSame('', $row[14]);            // Fecha matrícula vacía
    }
}
