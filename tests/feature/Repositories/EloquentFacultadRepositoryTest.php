<?php

namespace Tests\Feature\Repositories;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Universidad;
use App\Models\Facultad;
use App\Repositories\Eloquent\EloquentFacultadRepository;

class EloquentFacultadRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected EloquentFacultadRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new EloquentFacultadRepository();
    }

    /** @test */
    public function find_devuelve_null_si_no_existe_y_modelo_si_existe()
    {
        $this->assertNull($this->repo->find(9999));

        $u = Universidad::factory()->create();
        $f = Facultad::factory()->for($u)->create();

        $encontrada = $this->repo->find($f->id);
        $this->assertNotNull($encontrada);
        $this->assertEquals($f->id, $encontrada->id);
        $this->assertEquals($u->id, $encontrada->universidad_id);
    }

    /** @test */
    public function list_by_universidad_paginate_y_orden_desc_por_id()
    {
        $u1 = Universidad::factory()->create();
        $u2 = Universidad::factory()->create();

        // u1: 3 facultades
        $f1 = Facultad::factory()->for($u1)->create(['id' => 101]);
        $f2 = Facultad::factory()->for($u1)->create(['id' => 102]);
        $f3 = Facultad::factory()->for($u1)->create(['id' => 103]);

        // u2: 1 facultad (no debe aparecer cuando filtramos u1)
        Facultad::factory()->for($u2)->create();

        $page = $this->repo->listByUniversidad($u1->id, perPage: 2); // pagina de a 2
        $this->assertEquals(2, $page->perPage());
        $this->assertEquals(2, $page->count());
        // Orden latest('id') → desc: 103, 102 en la primera página
        $this->assertEquals([103, 102], $page->pluck('id')->all());
        $this->assertEquals(3, $page->total());

        $page2 = $this->repo->listByUniversidad($u1->id, perPage: 2)->setPageName('page')->appends(['page'=>2]);
        // Para simular página 2, mejor consulta directa:
        $page2 = Facultad::where('universidad_id', $u1->id)->latest('id')->paginate(2, ['*'], 'page', 2);
        $this->assertEquals([101], $page2->pluck('id')->all());
    }

    /** @test */
    public function create_inserta_registro_con_campos_minimos()
    {
        $u = \App\Models\Universidad::factory()->create();

        $nuevo = $this->repo->create([
            'universidad_id' => $u->id,
            'codigo' => 'FI-ING',                    // <-- OBLIGATORIO
            'nombre' => 'Facultad de Ingeniería',
        ]);

        $this->assertDatabaseHas('facultades', [
            'id' => $nuevo->id,
            'universidad_id' => $u->id,
            'codigo' => 'FI-ING',
            'nombre' => 'Facultad de Ingeniería',
        ]);
    }


    /** @test */
    public function update_modifica_campos_y_refresh_refleja_cambios()
    {
        $u = Universidad::factory()->create();
        $f = Facultad::factory()->for($u)->create([
            'nombre' => 'Facultad Antiguo',
        ]);

        $actualizada = $this->repo->update($f, ['nombre' => 'Facultad Nuevo']);
        $this->assertEquals('Facultad Nuevo', $actualizada->nombre);

        $this->assertDatabaseHas('facultades', [
            'id' => $f->id,
            'nombre' => 'Facultad Nuevo',
        ]);
    }

    /** @test */
    public function delete_elimina_el_registro()
    {
        $u = Universidad::factory()->create();
        $f = Facultad::factory()->for($u)->create();

        $this->repo->delete($f);

        $this->assertDatabaseMissing('facultades', ['id' => $f->id]);
    }

    /** @test */
    public function all_by_universidad_filtra_correctamente()
    {
        $u1 = Universidad::factory()->create();
        $u2 = Universidad::factory()->create();

        $f1 = Facultad::factory()->for($u1)->create();
        $f2 = Facultad::factory()->for($u1)->create();
        $f3 = Facultad::factory()->for($u2)->create();

        $lista = $this->repo->allByUniversidad($u1->id);
        $this->assertCount(2, $lista);
        $this->assertEqualsCanonicalizing([$f1->id, $f2->id], $lista->pluck('id')->all());
        $this->assertNotContains($f3->id, $lista->pluck('id')->all());
    }
}
