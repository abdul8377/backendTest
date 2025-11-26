<?php

namespace Tests\Unit\Services\EpSede;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\EpSede\StaffAssignmentService;
use App\Models\User;
use App\Models\EpSede;
use App\Models\ExpedienteAcademico;
use App\Models\EpSedeStaffHistorial;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;

class StaffAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected StaffAssignmentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StaffAssignmentService();

        // Disable all guards and validations for unit testing
        Config::set('ep_staff.touch_user_status', false);
        Config::set('ep_staff.block_suspended_users', false);
        Config::set('ep_staff.max_ep_coordinador', null);
        Config::set('ep_staff.cooldown_days', 0);
    }

    #[Test]
    public function current_returns_empty_when_no_assignments()
    {
        $epSede = EpSede::factory()->create();

        $result = $this->service->current($epSede->id);

        $this->assertNull($result['COORDINADOR']);
        $this->assertNull($result['ENCARGADO']);
    }

    #[Test]
    public function current_returns_active_assignments()
    {
        $coord = User::factory()->create();
        $encargado = User::factory()->create();
        $epSede = EpSede::factory()->create();

        ExpedienteAcademico::factory()->create([
            'user_id' => $coord->id,
            'ep_sede_id' => $epSede->id,
            'rol' => 'COORDINADOR',
            'estado' => 'ACTIVO',
        ]);

        ExpedienteAcademico::factory()->create([
            'user_id' => $encargado->id,
            'ep_sede_id' => $epSede->id,
            'rol' => 'ENCARGADO',
            'estado' => 'ACTIVO',
        ]);

        $result = $this->service->current($epSede->id);

        $this->assertEquals($coord->id, $result['COORDINADOR']['user_id']);
        $this->assertEquals($encargado->id, $result['ENCARGADO']['user_id']);
    }

    #[Test]
    public function history_returns_empty_array_when_no_logs()
    {
        $epSede = EpSede::factory()->create();

        $result = $this->service->history($epSede->id);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    #[Test]
    public function history_returns_logs_ordered_by_id_desc()
    {
        $epSede = EpSede::factory()->create();

        $log1 = EpSedeStaffHistorial::factory()->create([
            'ep_sede_id' => $epSede->id,
            'evento' => 'ASSIGN'
        ]);

        $log2 = EpSedeStaffHistorial::factory()->create([
            'ep_sede_id' => $epSede->id,
            'evento' => 'UNASSIGN'
        ]);

        $result = $this->service->history($epSede->id);

        $this->assertCount(2, $result);
        // Should be ordered by id desc, so log2 first
        $this->assertEquals($log2->id, $result[0]['id']);
        $this->assertEquals($log1->id, $result[1]['id']);
    }
}
