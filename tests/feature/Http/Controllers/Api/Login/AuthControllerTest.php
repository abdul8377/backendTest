<?php

namespace Tests\Feature\Http\Controllers\Api\Login;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, ExpedienteAcademico, EpSede, EscuelaProfesional, Sede, Facultad, Universidad};
use Illuminate\Support\Facades\Hash;

class AuthControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function test_lookup_finds_user_by_username()
    {
        $user = User::factory()->create(['username' => 'test.user']);

        $response = $this->postJson('/api/auth/lookup', ['username' => 'test.user']);

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'user' => [
                    'id' => $user->id,
                    'username' => 'test.user'
                ]
            ]);
    }

    /** @test */
    public function test_lookup_returns_404_for_nonexistent_user()
    {
        $response = $this->postJson('/api/auth/lookup', ['username' => 'nonexistent']);

        $response->assertNotFound()
            ->assertJson([
                'ok' => false,
                'message' => 'Usuario no encontrado.'
            ]);
    }

    /** @test */
    public function test_lookup_includes_active_expediente()
    {
        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);
        $epSede = EpSede::factory()->create([
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);

        $user = User::factory()->create(['username' => 'student']);
        ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'estado' => 'ACTIVO'
        ]);

        $response = $this->postJson('/api/auth/lookup', ['username' => 'student']);

        $response->assertOk()
            ->assertJsonStructure([
                'ok',
                'user',
                'academico' => ['id', 'ep_sede']
            ]);
    }

    /** @test */
    public function test_login_succeeds_with_valid_credentials()
    {
        $user = User::factory()->create([
            'username' => 'test.user',
            'password' => Hash::make('password123')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'test.user',
            'password' => 'password123'
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'ok',
                'token',
                'user' => ['id', 'username'],
                'academico'
            ]);

        $this->assertNotNull($response->json('token'));
    }

    /** @test */
    public function test_login_fails_with_invalid_password()
    {
        User::factory()->create([
            'username' => 'test.user',
            'password' => Hash::make('correct_password')
        ]);

        $response = $this->postJson('/api/auth/login', [
            'username' => 'test.user',
            'password' => 'wrong_password'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['credentials']);
    }

    /** @test */
    public function test_login_fails_with_nonexistent_user()
    {
        $response = $this->postJson('/api/auth/login', [
            'username' => 'nonexistent',
            'password' => 'password123'
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['credentials']);
    }

    /** @test */
    public function test_logout_revokes_current_token()
    {
        $user = User::factory()->create();
        $token = $user->createToken('api')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/auth/logout');

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'message' => 'Sesión cerrada.'
            ]);

        // Verificar que el token fue revocado
        $this->assertEquals(0, $user->tokens()->count());
    }

    /** @test */
    public function test_login_validates_required_fields()
    {
        $response = $this->postJson('/api/auth/login', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username', 'password']);
    }

    /** @test */
    public function test_lookup_validates_required_username()
    {
        $response = $this->postJson('/api/auth/lookup', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['username']);
    }
}
