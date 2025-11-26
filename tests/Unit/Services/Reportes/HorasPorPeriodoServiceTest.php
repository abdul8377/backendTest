<?php

namespace Tests\Unit\Services\Reportes;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Reportes\HorasPorPeriodoService;
use App\Models\EpSede;
use App\Models\PeriodoAcademico;
use PHPUnit\Framework\Attributes\Test;

class HorasPorPeriodoServiceTest extends TestCase
{
    use RefreshDatabase;

    protected HorasPorPeriodoService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HorasPorPeriodoService();
    }

    #[Test]
    public function build_returns_empty_data_when_no_periodos()
    {
        $epSede = EpSede::factory()->create();

        $result = $this->service->build(
            $epSede->id,
            null,
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertEmpty($result['data']);
        $this->assertEmpty($result['meta']['periodos']);
    }

    #[Test]
    public function build_returns_correct_structure_with_periodos()
    {
        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create([
            'codigo' => '2024-1',
            'anio' => 2024,
            'ciclo' => 1,
            'estado' => 'EN_CURSO'
        ]);

        $result = $this->service->build(
            $epSede->id,
            ['2024-1'],
            null
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertArrayHasKey('data', $result);
        $this->assertCount(1, $result['meta']['periodos']);
        $this->assertEquals('2024-1', $result['meta']['periodos'][0]['codigo']);
    }
}
