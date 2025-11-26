<?php

namespace App\Services;

class PaymentService
{
    protected $gateway;

    // Inyectamos una dependencia (por ejemplo, una clase que procesa pagos)
    // Esto es clave para poder usar Mockery: mockeamos esta dependencia.
    public function __construct($gateway)
    {
        $this->gateway = $gateway;
    }

    public function processPayment(float $amount): bool
    {
        if ($amount <= 0) {
            return false;
        }

        // Llamamos a un método de la dependencia
        return $this->gateway->charge($amount);
    }
}
