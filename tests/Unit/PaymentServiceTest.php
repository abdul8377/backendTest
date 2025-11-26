<?php

namespace Tests\Unit;

use App\Services\PaymentService;
use PHPUnit\Framework\TestCase;
use Mockery;

class PaymentServiceTest extends TestCase
{
    // Se ejecuta después de cada test para limpiar los mocks
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_processes_payment_successfully()
    {
        // 1. PREPARAR (Arrange)
        // Creamos un Mock de la dependencia (el gateway)
        // No necesitamos que la clase real exista, solo simulamos su comportamiento.
        $gatewayMock = Mockery::mock('PaymentGateway');

        // Definimos qué esperamos que haga el mock:
        // - Debe recibir el método 'charge'
        // - Con el argumento 100.0
        // - Y debe devolver 'true'
        $gatewayMock->shouldReceive('charge')
            ->once()
            ->with(100.0)
            ->andReturn(true);

        // Inyectamos el mock en nuestro servicio
        $service = new PaymentService($gatewayMock);

        // 2. ACTUAR (Act)
        $result = $service->processPayment(100.0);

        // 3. VERIFICAR (Assert)
        $this->assertTrue($result);
    }

    public function test_it_fails_if_amount_is_invalid()
    {
        // 1. PREPARAR
        $gatewayMock = Mockery::mock('PaymentGateway');

        // En este caso, NO esperamos que se llame a 'charge' porque el monto es inválido
        $gatewayMock->shouldNotReceive('charge');

        $service = new PaymentService($gatewayMock);

        // 2. ACTUAR
        $result = $service->processPayment(-50.0);

        // 3. VERIFICAR
        $this->assertFalse($result);
    }
}
