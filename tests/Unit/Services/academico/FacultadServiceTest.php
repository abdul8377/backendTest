<?php

namespace Tests\Unit\Services\Academico;

use App\Contracts\Repositories\FacultadRepository;
use App\Models\Facultad;
use App\Services\Academico\FacultadService;
use Illuminate\Validation\ValidationException;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FacultadServiceTest extends TestCase
{
    /** @var \Mockery\MockInterface&FacultadRepository */
    private $repo;

    private FacultadService $svc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = Mockery::mock(FacultadRepository::class);
        $this->svc  = new FacultadService($this->repo);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function crear_lanza_validation_si_falta_universidad_id(): void
    {
        $this->expectException(ValidationException::class);

        $this->svc->crear([
            'nombre' => 'Ingeniería',
            // 'universidad_id' faltante
        ]);
    }

    #[Test]
    public function crear_delega_en_repo_y_retorna_modelo(): void
    {
        $input = ['universidad_id' => 1, 'nombre' => 'Ingeniería'];
        $model = new Facultad();
        $model->id = 5;

        $this->repo->shouldReceive('create')
            ->once()
            ->with($input)
            ->andReturn($model);

        $res = $this->svc->crear($input);

        $this->assertSame($model, $res);
    }

    #[Test]
    public function actualizar_lanza_validation_si_no_existe(): void
    {
        $this->repo->shouldReceive('find')
            ->once()
            ->with(123)
            ->andReturn(null);

        $this->expectException(ValidationException::class);

        $this->svc->actualizar(123, ['nombre' => 'Ciencias']);
    }

    #[Test]
    public function actualizar_invoca_update_y_retorna_modelo(): void
    {
        $fac = new Facultad();
        $fac->id = 7;

        $updated = new Facultad();
        $updated->id = 7;
        $updated->nombre = 'Ciencias';

        $this->repo->shouldReceive('find')
            ->once()
            ->with(7)
            ->andReturn($fac);

        $this->repo->shouldReceive('update')
            ->once()
            ->with($fac, ['nombre' => 'Ciencias'])
            ->andReturn($updated);

        $res = $this->svc->actualizar(7, ['nombre' => 'Ciencias']);

        $this->assertSame($updated, $res);
        $this->assertEquals('Ciencias', $res->nombre);
    }

    #[Test]
    public function eliminar_no_falla_si_no_existe(): void
    {
        $this->repo->shouldReceive('find')
            ->once()
            ->with(9)
            ->andReturn(null);

        // No debe llamarse delete
        $this->repo->shouldNotReceive('delete');

        $this->svc->eliminar(9);

        $this->assertTrue(true); // llega aquí sin excepción
    }

    #[Test]
    public function eliminar_invoca_delete_si_existe(): void
    {
        $fac = new Facultad();
        $fac->id = 2;

        $this->repo->shouldReceive('find')
            ->once()
            ->with(2)
            ->andReturn($fac);

        $this->repo->shouldReceive('delete')
            ->once()
            ->with($fac)
            ->andReturnNull();

        $this->svc->eliminar(2);

        $this->assertTrue(true);
    }
}
