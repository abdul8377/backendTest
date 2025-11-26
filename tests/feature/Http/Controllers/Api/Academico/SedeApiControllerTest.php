<?php

namespace Tests\Feature\Http\Controllers\Api\Academico;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Sede, Universidad};
use Laravel\Sanctum\Sanctum;

class SedeApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Universidad $universidad;

    protected function setUp(): void
    {
        parent::setUp();

        // Crear rol ADMINISTRADOR
        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);

        // Crear usuario autenticado con rol ADMINISTRADOR
        $this->user = User::factory()->create();
        $this->user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($this->user);

        // Crear universidad base
        $this->universidad = Universidad::factory()->create();
    }

    /** @test */
    public function test_index_returns_paginated_sedes()
    {
        // Arrange
        Sede::factory()->count(3)->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->getJson('/api/administrador/academico/sedes');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'nombre', 'universidad_id', 'meta']
                ],
                'links',
                'meta'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function test_index_filters_by_search_query()
    {
        // Arrange
        Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Norte'
        ]);

        // Act
        $response = $this->getJson('/api/administrador/academico/sedes?q=Central');

        // Assert
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Sede Central', $response->json('data.0.nombre'));
    }

    /** @test */
    public function test_show_returns_sede()
    {
        // Arrange
        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->getJson("/api/administrador/academico/sedes/{$sede->id}");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $sede->id,
                    'nombre' => $sede->nombre,
                    'universidad_id' => $sede->universidad_id
                ]
            ]);
    }

    /** @test */
    public function test_store_creates_new_sede()
    {
        // Arrange
        $data = [
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central',
            'es_principal' => true,
            'esta_suspendida' => false
        ];

        // Act
        $response = $this->postJson('/api/administrador/academico/sedes', $data);

        // Assert
        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'nombre' => 'Sede Central'
                ]
            ]);

        $this->assertDatabaseHas('sedes', [
            'nombre' => 'Sede Central',
            'universidad_id' => $this->universidad->id,
            'es_principal' => true
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        // Act
        $response = $this->postJson('/api/administrador/academico/sedes', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['universidad_id', 'nombre']);
    }

    /** @test */
    public function test_store_validates_unique_nombre_per_universidad()
    {
        // Arrange
        Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        // Act - Intentar crear otra con mismo nombre en misma universidad
        $response = $this->postJson('/api/administrador/academico/sedes', [
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function test_store_allows_same_nombre_in_different_universidad()
    {
        // Arrange
        $otraUniversidad = Universidad::factory()->create();

        Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        // Act - Crear con mismo nombre pero en otra universidad
        $response = $this->postJson('/api/administrador/academico/sedes', [
            'universidad_id' => $otraUniversidad->id,
            'nombre' => 'Sede Central'
        ]);

        // Assert
        $response->assertCreated();
        $this->assertEquals(2, Sede::where('nombre', 'Sede Central')->count());
    }

    /** @test */
    public function test_update_modifies_existing_sede()
    {
        // Arrange
        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Norte'
        ]);

        $data = [
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Norte Actualizada',
            'es_principal' => false,
            'esta_suspendida' => false
        ];

        // Act
        $response = $this->putJson("/api/administrador/academico/sedes/{$sede->id}", $data);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'nombre' => 'Sede Norte Actualizada'
                ]
            ]);

        $this->assertDatabaseHas('sedes', [
            'id' => $sede->id,
            'nombre' => 'Sede Norte Actualizada'
        ]);
    }

    /** @test */
    public function test_update_validates_unique_excluding_self()
    {
        // Arrange
        $sede1 = Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        $sede2 = Sede::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Norte'
        ]);

        // Act - Intentar actualizar sede2 con nombre de sede1
        $response = $this->putJson("/api/administrador/academico/sedes/{$sede2->id}", [
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Sede Central'
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre']);
    }

    /** @test */
    public function test_destroy_deletes_sede()
    {
        // Arrange
        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->deleteJson("/api/administrador/academico/sedes/{$sede->id}");

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('sedes', ['id' => $sede->id]);
    }

    /** @test */
    public function test_escuelas_returns_escuelas_profesionales_of_sede()
    {
        // Arrange
        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->getJson("/api/administrador/academico/sedes/{$sede->id}/escuelas");

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'links',
                'meta'
            ]);
    }

    /** @test */
    public function test_endpoints_require_admin_role()
    {
        // Arrange - Usuario sin rol ADMINISTRADOR
        $userSinRol = User::factory()->create();
        Sanctum::actingAs($userSinRol);

        // Act & Assert - Debe retornar 403 Forbidden
        $this->getJson('/api/administrador/academico/sedes')->assertForbidden();
        $this->postJson('/api/administrador/academico/sedes', [])->assertForbidden();
    }
}
