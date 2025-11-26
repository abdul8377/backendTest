<?php

namespace Tests\Unit\Contracts;

use Tests\TestCase;
use App\Contracts\Repositories\FacultadRepository;
use ReflectionClass;

class FacultadRepositoryTest extends TestCase
{
    /** @test */
    public function it_defines_required_methods()
    {
        $reflection = new ReflectionClass(FacultadRepository::class);
        $expectedMethods = [
            'find',
            'listByUniversidad',
            'create',
            'update',
            'delete',
            'allByUniversidad',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "Method {$method} should exist in FacultadRepository contract");
        }
    }
}
