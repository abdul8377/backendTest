<?php

namespace Tests\Unit\Services\Vm;

use Tests\TestCase;
use App\Services\Vm\PlanificacionService;
use PHPUnit\Framework\Attributes\Test;

class PlanificacionServiceTest extends TestCase
{
    protected PlanificacionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlanificacionService();
    }

    #[Test]
    public function service_can_be_instantiated()
    {
        $this->assertInstanceOf(PlanificacionService::class, $this->service);
    }
}
