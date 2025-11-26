<?php

namespace Tests\Feature\Http\Controllers\Api\Academico;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, EscuelaProfesional, Facultad, Sede, Universidad};
use Laravel\Sanctum\Sanctum;

class EscuelaProfesionalApiControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Universidad $universidad;
    protected Facultad $facultad;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::create(['name' => 'ADMINISTRADOR', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('ADMINISTRADOR');
        Sanctum::actingAs($this->user);

        $this->universidad = Universidad::factory()->create();
        $this->facultad = Facultad::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);
    }

    /** @test */
    public function test_index_returns_paginated_escuelas()
    {
        EscuelaProfesional::factory()->count(3)->create([
            'facultad_id' => $this->facultad->id
        ]);

        $response = $this->getJson('/api/administrador/academico/escuelas-profesionales');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'codigo', 'nombre', 'facultad_id']
                ],
                'links',
                'meta'
            ]);

        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function test_index_filters_by_search_query()
    {
        EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id,
            'nombre' => 'Medicina Humana',
            'codigo' => 'MH'
        ]);

        EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id,
            'nombre' => 'Ingeniería de Sistemas',
            'codigo' => 'IS'
        ]);

        $response = $this->getJson('/api/administrador/academico/escuelas-profesionales?q=Medicina');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Medicina Humana', $response->json('data.0.nombre'));
    }

    /** @test */
    public function test_show_returns_escuela_with_relations()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $response = $this->getJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $escuela->id,
                    'codigo' => $escuela->codigo,
                    'nombre' => $escuela->nombre,
                    'facultad_id' => $escuela->facultad_id
                ]
            ]);
    }

    /** @test */
    public function test_store_creates_new_escuela()
    {
        $data = [
            'facultad_id' => $this->facultad->id,
            'codigo' => 'MH',
            'nombre' => 'Medicina Humana'
        ];

        $response = $this->postJson('/api/administrador/academico/escuelas-profesionales', $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'codigo' => 'MH',
                    'nombre' => 'Medicina Humana'
                ]
            ]);

        $this->assertDatabaseHas('escuelas_profesionales', [
            'codigo' => 'MH',
            'nombre' => 'Medicina Humana',
            'facultad_id' => $this->facultad->id
        ]);
    }

    /** @test */
    public function test_store_validates_required_fields()
    {
        $response = $this->postJson('/api/administrador/academico/escuelas-profesionales', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['facultad_id', 'codigo', 'nombre']);
    }

    /** @test */
    public function test_update_modifies_existing_escuela()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id,
            'codigo' => 'MH',
            'nombre' => 'Medicina'
        ]);

        $data = [
            'facultad_id' => $this->facultad->id,
            'codigo' => 'MH',
            'nombre' => 'Medicina Humana Actualizada'
        ];

        $response = $this->putJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}", $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'nombre' => 'Medicina Humana Actualizada'
                ]
            ]);

        $this->assertDatabaseHas('escuelas_profesionales', [
            'id' => $escuela->id,
            'nombre' => 'Medicina Humana Actualizada'
        ]);
    }

    /** @test */
    public function test_destroy_deletes_escuela()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $response = $this->deleteJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('escuelas_profesionales', ['id' => $escuela->id]);
    }

    /** @test */
    public function test_attach_sede_creates_relationship()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        $data = [
            'sede_id' => $sede->id,
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => now()->addYear()->toDateString()
        ];

        $response = $this->postJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}/sedes", $data);

        $response->assertCreated();

        $this->assertDatabaseHas('ep_sede', [
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);
    }

    /** @test */
    public function test_attach_sede_prevents_duplicates()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        // Attach primera vez
        $escuela->sedes()->attach($sede->id);

        // Intentar attach nuevamente
        $data = ['sede_id' => $sede->id];
        $response = $this->postJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}/sedes", $data);

        $response->assertStatus(409); // Conflict
    }

    /** @test */
    public function test_update_sede_vigencia_modifies_dates()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        $escuela->sedes()->attach($sede->id, [
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => now()->addMonths(6)->toDateString()
        ]);

        $newData = [
            'vigente_desde' => now()->toDateString(),
            'vigente_hasta' => now()->addYear()->toDateString()
        ];

        $response = $this->putJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}/sedes/{$sede->id}", $newData);

        $response->assertNoContent();

        $this->assertDatabaseHas('ep_sede', [
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id,
            'vigente_hasta' => now()->addYear()->toDateString()
        ]);
    }

    /** @test */
    public function test_detach_sede_removes_relationship()
    {
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $this->facultad->id
        ]);

        $sede = Sede::factory()->create([
            'universidad_id' => $this->universidad->id
        ]);

        $escuela->sedes()->attach($sede->id);

        $response = $this->deleteJson("/api/administrador/academico/escuelas-profesionales/{$escuela->id}/sedes/{$sede->id}");

        $response->assertNoContent();

        $this->assertDatabaseMissing('ep_sede', [
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);
    }

    /** @test */
    public function test_endpoints_require_admin_role()
    {
        $userSinRol = User::factory()->create();
        Sanctum::actingAs($userSinRol);

        $this->getJson('/api/administrador/academico/escuelas-profesionales')->assertForbidden();
        $this->postJson('/api/administrador/academico/escuelas-profesionales', [])->assertForbidden();
    }
}
