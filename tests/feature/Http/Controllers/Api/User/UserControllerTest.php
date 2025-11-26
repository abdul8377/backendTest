<?php

namespace Tests\Feature\Http\Controllers\Api\User;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Illuminate\Support\Facades\Hash;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    /** @test */
    public function test_me_returns_current_user_profile()
    {
        $response = $this->getJson('/api/users/me');

        $response->assertOk()
            ->assertJson([
                'id' => $this->user->id,
                'username' => $this->user->username,
                'email' => $this->user->email
            ]);
    }

    /** @test */
    public function test_update_me_modifies_profile()
    {
        $data = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com'
        ];

        $response = $this->putJson('/api/users/me', $data);

        $response->assertOk();

        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'email' => 'updated@test.com'
        ]);
    }

    /** @test */
    public function test_update_my_password_changes_password()
    {
        $data = [
            'current_password' => 'password',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ];

        $response = $this->putJson('/api/users/me/password', $data);

        $response->assertOk();

        $this->assertTrue(Hash::check('newpassword123', $this->user->fresh()->password));
    }

    /** @test */
    public function test_show_by_username_finds_user()
    {
        $otherUser = User::factory()->create(['username' => 'findme']);

        $response = $this->getJson('/api/users/by-username/findme');

        $response->assertOk()
            ->assertJson(['username' => 'findme']);
    }

    /** @test */
    public function test_sessions_returns_user_sessions()
    {
        // Crear algunos tokens
        $this->user->createToken('device1');
        $this->user->createToken('device2');

        $response = $this->getJson('/api/users/me/sessions');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_destroy_session_revokes_token()
    {
        $token = $this->user->createToken('test');
        $tokenId = $token->accessToken->id;

        $response = $this->deleteJson("/api/users/me/sessions/{$tokenId}");

        $response->assertOk();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenId]);
    }

    /** @test */
    public function test_endpoints_require_authentication()
    {
        Sanctum::actingAs(null);

        $this->getJson('/api/users/me')->assertUnauthorized();
        $this->putJson('/api/users/me', [])->assertUnauthorized();
    }
}
