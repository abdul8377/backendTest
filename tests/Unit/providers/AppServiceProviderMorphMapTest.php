<?php
// tests/Unit/Providers/AppServiceProviderMorphMapTest.php

namespace Tests\Unit\Providers;

use Tests\TestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use App\Models\User;
use App\Models\VmProyecto;
use App\Models\VmProceso;
use App\Models\VmEvento;
use App\Models\EpSede;
use App\Models\Sede;
use App\Models\Facultad;
use App\Models\Universidad;

class AppServiceProviderMorphMapTest extends TestCase
{
    /** @test */
    public function canonical_aliases_resolve_to_expected_classes()
    {
        $cases = [
            'user'        => User::class,
            'vm_proyecto' => VmProyecto::class,
            'vm_proceso'  => VmProceso::class,
            'vm_evento'   => VmEvento::class,
            'ep_sede'     => EpSede::class,
            'sede'        => Sede::class,
            'facultad'    => Facultad::class,
            'universidad' => Universidad::class,
        ];

        foreach ($cases as $alias => $class) {
            $this->assertSame($class, Relation::getMorphedModel($alias), "Alias {$alias} no resuelve a {$class}");
        }
    }

    /** @test */
    public function backward_compat_aliases_resolve_to_expected_classes()
    {
        $cases = [
            'App\\Models\\User'        => User::class,
            'App\\Models\\VmProyecto'  => VmProyecto::class,
            'App\\Models\\VmProceso'   => VmProceso::class,
            'App\\Models\\VmEvento'    => VmEvento::class,
            'App\\Models\\EpSede'      => EpSede::class,
            'App\\Models\\Sede'        => Sede::class,
            'App\\Models\\Facultad'    => Facultad::class,
            'App\\Models\\Universidad' => Universidad::class,
            'VmProceso'                => VmProceso::class,
            'VmEvento'                 => VmEvento::class,
            'Universidad'              => Universidad::class,
        ];

        foreach ($cases as $alias => $class) {
            $this->assertSame($class, Relation::getMorphedModel($alias), "Legacy {$alias} no resuelve a {$class}");
        }
    }

    /** @test */
    public function get_morph_class_returns_alias_instead_of_fqcn_when_mapped()
    {
        // Model::getMorphClass() debe devolver el ALIAS canónico si está en el mapa.
        $this->assertSame('universidad', (new Universidad)->getMorphClass());
        $this->assertSame('user', (new User)->getMorphClass());
        $this->assertSame('vm_proyecto', (new VmProyecto)->getMorphClass());
    }

    /** @test */
    public function unknown_alias_is_not_mapped()
    {
        $this->assertNull(Relation::getMorphedModel('lo_que_sea_no_mapeado'));
    }
}
