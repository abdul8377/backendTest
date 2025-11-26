<?php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use App\Contracts\Repositories\FacultadRepository;
use App\Repositories\Eloquent\EloquentFacultadRepository;
use App\Providers\RepositoryServiceProvider;

class RepositoryServiceProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // registra explícitamente el provider para este test
        $this->app->register(RepositoryServiceProvider::class);
    }

    /** @test */
    public function container_resuelve_la_interfaz_al_repo_eloquent()
    {
        $repo = $this->app->make(FacultadRepository::class);

        $this->assertInstanceOf(EloquentFacultadRepository::class, $repo);
        $this->assertInstanceOf(FacultadRepository::class, $repo);
    }

    /** @test */
    public function bind_por_defecto_no_es_singleton()
    {
        $a = $this->app->make(FacultadRepository::class);
        $b = $this->app->make(FacultadRepository::class);

        $this->assertNotSame($a, $b);
    }
}
