<?php

namespace Tests\Feature\Http\Controllers\Api\Academico;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, Facultad, Universidad};
use Laravel\Sanctum\Sanctum;

class FacultadApiControllerTest extends TestCase
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
    public function test_index_returns_paginated_facultades()
    {
        // Arrange
        Facultad::factory()->count(3)->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->getJson('/api/administrador/academico/facultades');

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'codigo', 'nombre', 'universidad_id', 'meta']
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
        Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Medicina',
            'codigo' => 'MED'
        ]);

        Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'nombre' => 'Ingeniería',
            'codigo' => 'ING'
        ]);

        // Act
        $response = $this->getJson('/api/administrador/academico/facultades?q=Medicina');

        // Assert
        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Medicina', $response->json('data.0.nombre'));
    }

    /** @test */
    public function test_show_returns_facultad_with_escuelas()
    {
        // Arrange
        $facultad = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->getJson("/api/administrador/academico/facultades/{$facultad->id}");

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $facultad->id,
                    'codigo' => $facultad->codigo,
                    'nombre' => $facultad->nombre,
                    'universidad_id' => $facultad->universidad_id
                ]
            ]);
    }

    /** @test */
    public function test_store_creates_new_facultad()
    {
        // Arrange
        $data = [
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Facultad de Medicina'
        ];

        // Act
        $response = $this->postJson('/api/administrador/academico/facultades', $data);

        // Assert
        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'codigo' => 'MED',
                    'nombre' => 'Facultad de Medicina'
                ]
            ]);

        $this->assertDatabaseHas('facultades', [
            'codigo' => 'MED',
            'nombre' => 'Facultad de Medicina',
            'universidad_id' => $this->universidad->id
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        // Act
        $response = $this->postJson('/api/administrador/academico/facultades', []);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['universidad_id', 'codigo', 'nombre']);
    }

    /** @test */
    public function test_store_validates_unique_codigo_per_universidad()
    {
        // Arrange
        Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina'
        ]);

        // Act - Intentar crear otra con mismo código en misma universidad
        $response = $this->postJson('/api/administrador/academico/facultades', [
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina Humana'
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function test_store_allows_same_codigo_in_different_universidad()
    {
        // Arrange
        $otraUniversidad = Universidad::factory()->create();

        Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina'
        ]);

        // Act - Crear con mismo código pero en otra universidad
        $response = $this->postJson('/api/administrador/academico/facultades', [
            'universidad_id' => $otraUniversidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina'
        ]);

        // Assert
        $response->assertCreated();
        $this->assertEquals(2, Facultad::where('codigo', 'MED')->count());
    }

    /** @test */
    public function test_update_modifies_existing_facultad()
    {
        // Arrange
        $facultad = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina'
        ]);

        $data = [
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Facultad de Medicina Humana'
        ];

        // Act
        $response = $this->putJson("/api/administrador/academico/facultades/{$facultad->id}", $data);

        // Assert
        $response->assertOk()
            ->assertJson([
                'data' => [
                    'nombre' => 'Facultad de Medicina Humana'
                ]
            ]);

        $this->assertDatabaseHas('facultades', [
            'id' => $facultad->id,
            'nombre' => 'Facultad de Medicina Humana'
        ]);
    }

    /** @test */
    public function test_update_validates_unique_excluding_self()
    {
        // Arrange
        $facultad1 = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Medicina'
        ]);

        $facultad2 = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id,
            'codigo' => 'ING',
            'nombre' => 'Ingeniería'
        ]);

        // Act - Intentar actualizar facultad2 con código de facultad1
        $response = $this->putJson("/api/administrador/academico/facultades/{$facultad2->id}", [
            'universidad_id' => $this->universidad->id,
            'codigo' => 'MED',
            'nombre' => 'Ingeniería'
        ]);

        // Assert
        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['codigo']);
    }

    /** @test */
    public function test_destroy_deletes_facultad()
    {
        // Arrange
        $facultad = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Act
        $response = $this->deleteJson("/api/administrador/academico/facultades/{$facultad->id}");

        // Assert
        $response->assertNoContent();
        $this->assertDatabaseMissing('facultades', ['id' => $facultad->id]);
    }

    /** @test */
    public function test_endpoints_require_admin_role()
    {
        // Arrange - Usuario sin rol ADMINISTRADOR
        $userSinRol = User::factory()->create();
        Sanctum::actingAs($userSinRol);

        // Act & Assert - Debe retornar 403 Forbidden
        $this->getJson('/api/administrador/academico/facultades')->assertForbidden();
        $this->postJson('/api/administrador/academico/facultades', [])->assertForbidden();
    }
}
