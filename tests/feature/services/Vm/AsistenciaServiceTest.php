<?php

namespace Tests\Feature\Services\Vm;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use App\Services\Vm\AsistenciaService;
use App\Models\{
    User,
    EpSede,
    PeriodoAcademico,
    ExpedienteAcademico,
    VmProyecto,
    VmProceso,
    VmSesion,
    VmQrToken,
    VmAsistencia,
    VmParticipacion,
    RegistroHora
};
use Carbon\Carbon;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class AsistenciaServiceTest extends TestCase
{
    use RefreshDatabase;

    private AsistenciaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::create(['name' => 'ep.manage.ep_sede']);
        $this->service = app(AsistenciaService::class);
    }

    /** @test */
    public function test_genera_token_qr_para_sesion_con_permisos()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
        ]);

        // Act
        $token = $this->service->generarToken(
            $actor,
            $sesion,
            'QR',
            ['lat' => -12.0464, 'lng' => -77.0428, 'radio_m' => 100]
        );

        // Assert
        $this->assertInstanceOf(VmQrToken::class, $token);
        $this->assertEquals($sesion->id, $token->sesion_id);
        $this->assertEquals('QR', $token->tipo);
        $this->assertEquals(-12.0464, $token->lat);
        $this->assertEquals(-77.0428, $token->lng);
        $this->assertEquals(100, $token->radio_m);
        $this->assertTrue($token->activo);
        $this->assertEquals(0, $token->usos);
    }

    /** @test */
    public function test_genera_token_manual_alineado_a_sesion()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
            'fecha' => '2025-12-01',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '16:00:00',
        ]);

        // Act
        $token = $this->service->generarTokenManualAlineado($actor, $sesion);

        // Assert
        $this->assertEquals('MANUAL', $token->tipo);
        $this->assertEquals($sesion->id, $token->sesion_id);

        // Verify the token is aligned to session bounds
        [$start, $end] = $this->service->boundsForSesion($sesion);
        $this->assertEquals($start->toDateTimeString(), $token->usable_from->toDateTimeString());
        $this->assertEquals($end->toDateTimeString(), $token->expires_at->toDateTimeString());
    }

    /** @test */
    public function test_genera_token_sin_permisos_lanza_excepcion()
    {
        // Arrange
        $actor = User::factory()->create(); // Sin permisos

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
        ]);

        // Act & Assert
        $this->expectException(AuthorizationException::class);
        $this->service->generarToken($actor, $sesion);
    }

    /** @test */
    public function test_check_ventana_valida_token_activo()
    {
        // Arrange
        $token = new VmQrToken([
            'activo' => true,
            'usable_from' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(10),
            'max_usos' => 50,
            'usos' => 10,
        ]);

        // Act & Assert - No debe lanzar excepción
        $this->service->checkVentana($token);
        $this->assertTrue(true); // Si llegamos aquí, la validación pasó
    }

    /** @test */
    public function test_check_ventana_rechaza_token_expirado()
    {
        // Arrange
        $token = new VmQrToken([
            'activo' => true,
            'usable_from' => now()->subHours(2),
            'expires_at' => now()->subHour(),
            'max_usos' => null,
            'usos' => 0,
        ]);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->service->checkVentana($token);
    }

    /** @test */
    public function test_check_ventana_rechaza_token_sin_cupo()
    {
        // Arrange
        $token = new VmQrToken([
            'activo' => true,
            'usable_from' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(10),
            'max_usos' => 5,
            'usos' => 5,
        ]);

        // Act & Assert
        $this->expectException(ValidationException::class);
        $this->service->checkVentana($token);
    }

    /** @test */
    public function test_check_geofence_valida_ubicacion_dentro_del_radio()
    {
        // Arrange - Lima centro
        $token = new VmQrToken([
            'lat' => -12.0464,
            'lng' => -77.0428,
            'radio_m' => 1000, // 1km
        ]);

        // Act & Assert - Ubicación muy cercana (mismo punto)
        $this->service->checkGeofence($token, -12.0464, -77.0428);
        $this->assertTrue(true);
    }

    /** @test */
    public function test_check_geofence_rechaza_ubicacion_fuera_del_radio()
    {
        // Arrange
        $token = new VmQrToken([
            'lat' => -12.0464,
            'lng' => -77.0428,
            'radio_m' => 100, // 100m
        ]);

        // Act & Assert - Ubicación lejana (aprox 5km)
        $this->expectException(ValidationException::class);
        $this->service->checkGeofence($token, -12.0900, -77.0428);
    }

    /** @test */
    public function test_upsert_asistencia_con_token_qr()
    {
        // Arrange
        $actor = User::factory()->create();

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
        ]);

        $expediente = ExpedienteAcademico::factory()->create([
            'user_id' => $actor->id,
            'ep_sede_id' => $epSede->id,
        ]);

        $token = VmQrToken::create([
            'sesion_id' => $sesion->id,
            'token' => bin2hex(random_bytes(16)),
            'tipo' => 'QR',
            'usable_from' => now()->subMinutes(10),
            'expires_at' => now()->addMinutes(10),
            'usos' => 0,
            'activo' => true,
        ]);

        // Crear participación
        VmParticipacion::create([
            'participable_type' => VmProyecto::class,
            'participable_id' => $proyecto->id,
            'expediente_id' => $expediente->id,
        ]);

        // Act
        $asistencia = $this->service->upsertAsistencia(
            $actor,
            $sesion,
            $expediente,
            'QR',
            $token,
            ['device' => 'mobile']
        );

        // Assert
        $this->assertInstanceOf(VmAsistencia::class, $asistencia);
        $this->assertEquals($sesion->id, $asistencia->sesion_id);
        $this->assertEquals($expediente->id, $asistencia->expediente_id);
        $this->assertEquals('QR', $asistencia->metodo);
        $this->assertEquals($token->id, $asistencia->qr_token_id);
        $this->assertNotNull($asistencia->check_in_at);

        // Verificar que el token incrementó sus usos
        $this->assertEquals(1, $token->fresh()->usos);
    }

    /** @test */
    public function test_upsert_asistencia_manual_requiere_permisos()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
        ]);

        $expediente = ExpedienteAcademico::factory()->create([
            'ep_sede_id' => $epSede->id,
        ]);

        // Act
        $asistencia = $this->service->upsertAsistencia(
            $actor,
            $sesion,
            $expediente,
            'MANUAL',
            null
        );

        // Assert
        $this->assertEquals('MANUAL', $asistencia->metodo);
        $this->assertNull($asistencia->qr_token_id);
    }

    /** @test */
    public function test_validar_asistencia_crea_registro_hora()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $epSede = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();
        $proyecto = VmProyecto::factory()->create([
            'ep_sede_id' => $epSede->id,
            'periodo_id' => $periodo->id,
        ]);
        $proceso = VmProceso::factory()->create(['proyecto_id' => $proyecto->id]);
        $sesion = VmSesion::factory()->create([
            'sessionable_type' => VmProceso::class,
            'sessionable_id' => $proceso->id,
            'fecha' => '2025-12-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
        ]);

        $expediente = ExpedienteAcademico::factory()->create([
            'ep_sede_id' => $epSede->id,
        ]);

        $asistencia = VmAsistencia::create([
            'sesion_id' => $sesion->id,
            'expediente_id' => $expediente->id,
            'check_in_at' => now(),
            'estado' => 'PENDIENTE',
            'metodo' => 'QR',
        ]);

        // Act
        $resultado = $this->service->validarAsistencia($actor, $asistencia, 120, true);

        // Assert
        $this->assertEquals('VALIDADO', $resultado->estado);
        $this->assertEquals(120, $resultado->minutos_validados);
        $this->assertNotNull($resultado->check_out_at);

        // Verificar que se creó el registro de horas
        $registroHora = RegistroHora::where('asistencia_id', $asistencia->id)->first();
        $this->assertNotNull($registroHora);
        $this->assertEquals(120, $registroHora->minutos);
        $this->assertEquals('APROBADO', $registroHora->estado);
        $this->assertEquals($expediente->id, $registroHora->expediente_id);
        $this->assertEquals($epSede->id, $registroHora->ep_sede_id);
        $this->assertEquals($periodo->id, $registroHora->periodo_id);
    }

    /** @test */
    public function test_bounds_for_sesion_calcula_correctamente()
    {
        // Arrange
        $sesion = VmSesion::factory()->make([
            'fecha' => '2025-12-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:00:00',
        ]);

        // Act
        [$start, $end] = $this->service->boundsForSesion($sesion);

        // Assert
        $this->assertEquals('2025-12-01 10:00:00', $start->toDateTimeString());
        $this->assertEquals('2025-12-01 12:00:00', $end->toDateTimeString());
    }

    /** @test */
    public function test_bounds_for_sesion_maneja_cruce_de_medianoche()
    {
        // Arrange
        $sesion = VmSesion::factory()->make([
            'fecha' => '2025-12-01',
            'hora_inicio' => '22:00:00',
            'hora_fin' => '02:00:00', // Cruza medianoche
        ]);

        // Act
        [$start, $end] = $this->service->boundsForSesion($sesion);

        // Assert
        $this->assertEquals('2025-12-01 22:00:00', $start->toDateTimeString());
        $this->assertEquals('2025-12-02 02:00:00', $end->toDateTimeString()); // Día siguiente
    }

    /** @test */
    public function test_minutos_sesion_calcula_duracion()
    {
        // Arrange
        $sesion = VmSesion::factory()->make([
            'fecha' => '2025-12-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '12:30:00',
        ]);

        // Act
        $minutos = $this->service->minutosSesion($sesion);

        // Assert
        $this->assertEquals(150, $minutos); // 2.5 horas = 150 minutos
    }

    /** @test */
    public function test_resolver_expediente_por_user_propio()
    {
        // Arrange
        $user = User::factory()->create();
        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
        ]);

        // Act - Usuario consultando su propio expediente
        $resultado = $this->service->resolverExpedientePorUser($user, $user, $epSede->id);

        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals($expediente->id, $resultado->id);
    }

    /** @test */
    public function test_resolver_expediente_por_user_tercero_requiere_permisos()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $otroUser = User::factory()->create();
        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'user_id' => $otroUser->id,
            'ep_sede_id' => $epSede->id,
        ]);

        // Act
        $resultado = $this->service->resolverExpedientePorUser($actor, $otroUser, $epSede->id);

        // Assert
        $this->assertNotNull($resultado);
        $this->assertEquals($expediente->id, $resultado->id);
    }

    /** @test */
    public function test_resolver_expediente_por_identificador()
    {
        // Arrange
        $actor = User::factory()->create();
        $actor->givePermissionTo('ep.manage.ep_sede');

        $user = User::factory()->create(['doc_numero' => '12345678']);
        $epSede = EpSede::factory()->create();
        $expediente = ExpedienteAcademico::factory()->create([
            'user_id' => $user->id,
            'ep_sede_id' => $epSede->id,
            'codigo_estudiante' => '2020123456',
        ]);

        // Act - Buscar por DNI
        $resultadoDni = $this->service->resolverExpedientePorIdentificador($actor, '12345678', $epSede->id);

        // Act - Buscar por código
        $resultadoCodigo = $this->service->resolverExpedientePorIdentificador($actor, '2020123456', $epSede->id);

        // Assert
        $this->assertNotNull($resultadoDni);
        $this->assertEquals($expediente->id, $resultadoDni->id);

        $this->assertNotNull($resultadoCodigo);
        $this->assertEquals($expediente->id, $resultadoCodigo->id);
    }
}
