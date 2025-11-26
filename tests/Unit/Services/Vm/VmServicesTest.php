<?php

namespace Tests\Unit\Services\Vm;

use Tests\TestCase;
use App\Services\Vm\AsistenciaService;
use App\Services\Vm\EstadoService;
use App\Services\Vm\SesionBatchService;
use PHPUnit\Framework\Attributes\Test;

class VmServicesTest extends TestCase
{
    #[Test]
    public function asistencia_service_can_be_instantiated()
    {
        $service = new AsistenciaService();
        $this->assertInstanceOf(AsistenciaService::class, $service);
    }

    #[Test]
    public function estado_service_can_be_instantiated()
    {
        $service = new EstadoService();
        $this->assertInstanceOf(EstadoService::class, $service);
    }

    #[Test]
    public function sesion_batch_service_can_be_instantiated()
    {
        $service = new SesionBatchService();
        $this->assertInstanceOf(SesionBatchService::class, $service);
    }
}
