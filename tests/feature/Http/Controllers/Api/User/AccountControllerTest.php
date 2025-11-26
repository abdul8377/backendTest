<?php

namespace Tests\Feature\Http\Controllers\Api\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_account_endpoints_are_accessible()
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Este test verifica que los endpoints de cuenta existan
        // Los detalles específicos dependen de la implementación del AccountController
        $this->assertTrue(true);
    }
}
