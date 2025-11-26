<?php

namespace Tests\Feature\Http\Controllers\Api\Lookup;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\{User, EpSede, PeriodoAcademico, EscuelaProfesional, Sede, Facultad, Universidad};
use Laravel\Sanctum\Sanctum;

class LookupControllerTest extends TestCase
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
    public function test_ep_sedes_returns_list()
    {
        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create(['facultad_id' => $facultad->id]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);

        EpSede::factory()->count(3)->create([
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);

        $response = $this->getJson('/api/lookups/ep-sedes');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_periodos_returns_academic_periods()
    {
        PeriodoAcademico::factory()->count(3)->create();

        $response = $this->getJson('/api/lookups/periodos');

        $response->assertOk()
            ->assertJsonStructure(['data' => []]);
    }

    /** @test */
    public function test_ep_sedes_filters_by_query()
    {
        $universidad = Universidad::factory()->create();
        $facultad = Facultad::factory()->create(['universidad_id' => $universidad->id]);
        $escuela = EscuelaProfesional::factory()->create([
            'facultad_id' => $facultad->id,
            'nombre' => 'Medicina Humana'
        ]);
        $sede = Sede::factory()->create(['universidad_id' => $universidad->id]);

        EpSede::factory()->create([
            'escuela_profesional_id' => $escuela->id,
            'sede_id' => $sede->id
        ]);

        $response = $this->getJson('/api/lookups/ep-sedes?q=Medicina');

        $response->assertOk();
    }

    /** @test */
    public function test_lookups_require_authentication()
    {
        Sanctum::actingAs(null);

        $this->getJson('/api/lookups/ep-sedes')->assertUnauthorized();
        $this->getJson('/api/lookups/periodos')->assertUnauthorized();
    }
}
