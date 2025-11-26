<?php

namespace Tests\Feature\Imports;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use App\Imports\MatriculaRegistroImport;
use App\Models\User;
use App\Models\EpSede;
use App\Models\PeriodoAcademico;
use App\Models\Matricula;

class MatriculaRegistroImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Rol requerido por ensureStudentRole()
        Role::firstOrCreate(['name' => 'ESTUDIANTE', 'guard_name' => 'web']);

        // Para que el hashing sea rápido en tests
        Hash::setRounds((int) env('BCRYPT_ROUNDS', 4));
    }

    /** @test */
    public function importa_crea_usuario_expediente_y_matricula()
    {
        // Arrange
        $epSede  = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create([
            'estado' => 'EN_CURSO',
        ]);

        $import = new MatriculaRegistroImport($epSede->id, $periodo->id);

        $rows = collect([
            [
                'APELLIDOS Y NOMBRES' => 'PEREZ GOMEZ, JUAN CARLOS',
                'dni'                 => '12345678',
                'correo'              => 'juan@example.com',
                'usuario'             => 'jperez',
                'codigo estudiante'   => '20201234',
                'ciclo'               => '3',
                'grupo'               => 'A1',
                'modalidad estudio'   => 'Presencial',
                'modo contrato'       => 'Regular',
                'fecha de matrícula'  => '2025-03-15 10:30',
                'pais'                => 'Perú',
                'correo institucional'=> 'juan@upeu.edu.pe',
            ],
            // sin fecha -> no crea matrícula; user queda view_only, expediente SUSPENDIDO
            [
                'apellidos y nombres' => 'YUPANQUI BRAVO, BRUNO',
                'dni'                 => '87654321',
                'usuario'             => 'byupanqui',
                'codigo estudiante'   => '20205678',
                'ciclo'               => '2',
                'grupo'               => 'B1',
                'modalidad estudio'   => 'Virtual',
                'modo contrato'       => 'Convenio',
                'fecha de matrícula'  => '',
                'pais'                => 'Peru',
            ],
        ]);

        // Act
        $import->collection($rows);
        $summary = $import->summary();

        // Assert summary
        $this->assertSame(2, $summary['processed']);
        $this->assertSame(2, $summary['created_users']);
        $this->assertSame(2, $summary['created_expedientes']);
        $this->assertSame(1, $summary['created_matriculas']);
        $this->assertSame(0, $summary['errors']);

        // Usuario 1
        $u1 = User::where('username', 'jperez')->first();
        $this->assertNotNull($u1);
        $this->assertTrue($u1->hasRole('ESTUDIANTE'));
        $this->assertSame('active', $u1->status);

        $this->assertDatabaseHas('expedientes_academicos', [
            'user_id'              => $u1->id,
            'ep_sede_id'           => $epSede->id,
            'codigo_estudiante'    => '20201234',
            'estado'               => 'ACTIVO',
            'ciclo'                => '3',
            'grupo'                => 'A1',
            'correo_institucional' => 'juan@upeu.edu.pe',
        ]);

        // Verificación robusta de la matrícula (sin comparar directamente la columna date con formato exacto)
        $this->assertDatabaseHas('matriculas', [
            'periodo_id'        => $periodo->id,
            'ciclo'             => 3,
            'grupo'             => 'A1',
            'modalidad_estudio' => 'PRESENCIAL',
            'modo_contrato'     => 'REGULAR',
        ]);

        $this->assertTrue(
            Matricula::query()
                ->where('periodo_id', $periodo->id)
                ->where('ciclo', 3)
                ->where('grupo', 'A1')
                ->where('modalidad_estudio', 'PRESENCIAL')
                ->where('modo_contrato', 'REGULAR')
                ->whereDate('fecha_matricula', '2025-03-15')
                ->exists(),
            'No se encontró matrícula con fecha 2025-03-15'
        );

        // Usuario 2 (sin fecha)
        $u2 = User::where('username', 'byupanqui')->first();
        $this->assertNotNull($u2);
        $this->assertTrue($u2->hasRole('ESTUDIANTE'));
        $this->assertSame('view_only', $u2->status);

        $this->assertDatabaseHas('expedientes_academicos', [
            'user_id' => $u2->id,
            'estado'  => 'SUSPENDIDO',
            'ciclo'   => '2',
            'grupo'   => 'B1',
        ]);

        $this->assertFalse(
            Matricula::query()
                ->where('periodo_id', $periodo->id)
                ->where('expediente_id', $u2->expedientesAcademicos()->value('id'))
                ->exists(),
            'No debería existir matrícula para el segundo usuario'
        );
    }

    /** @test */
    public function actualiza_usuario_y_mueve_expediente_de_ep_sede_y_upsertea_matricula()
    {
        // Arrange
        $epA     = EpSede::factory()->create();
        $epB     = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();

        // Usuario con expediente previo en epA y sin password (para exercise de ensurePassword)
        $u = User::factory()->create([
            'username' => 'azapata',
            'email'    => 'ana@example.com',
            'password' => '', // fuerza ensurePassword()
        ]);

        $exp = $u->expedientesAcademicos()->create([
            'ep_sede_id'         => $epA->id,
            'codigo_estudiante'  => '20209999',
            'estado'             => 'SUSPENDIDO',
            'rol'                => 'ESTUDIANTE',
            'ciclo'              => '1',
        ]);

        $import = new MatriculaRegistroImport($epB->id, $periodo->id);

        $rows = new Collection([[
            'apellidos y nombres' => 'ZAPATA, ANA',
            'dni'                 => '44556677',
            'usuario'             => 'azapata',
            'codigo estudiante'   => '20209999',
            'ciclo'               => '2',
            'grupo'               => 'B1',
            'modalidad estudio'   => 'Virtual',
            'modo contrato'       => 'Convenio',
            'fecha de matrícula'  => '2025-08-05',
            'pais'                => 'Colombia',
        ]]);

        // Act
        $import->collection($rows);
        $summary = $import->summary();

        // Assert summary
        $this->assertSame(1, $summary['processed']);
        $this->assertSame(0, $summary['created_users']);
        $this->assertSame(1, $summary['updated_users']);

        // Aquí suelen contarse 2 updates: mover EP-SEDE + actualizar estado/ciclo
        $this->assertSame(2, $summary['updated_expedientes']);
        $this->assertSame(1, $summary['created_matriculas']);

        // Expediente movido a epB y actualizado
        $this->assertDatabaseHas('expedientes_academicos', [
            'id'        => $exp->id,
            'ep_sede_id'=> $epB->id,
            'estado'    => 'ACTIVO',
            'ciclo'     => '2',
        ]);

        // Matrícula creada con normalizaciones
        $this->assertDatabaseHas('matriculas', [
            'expediente_id'     => $exp->id,
            'periodo_id'        => $periodo->id,
            'modalidad_estudio' => 'VIRTUAL',
            'modo_contrato'     => 'CONVENIO',
        ]);

        $this->assertTrue(
            Matricula::query()
                ->where('expediente_id', $exp->id)
                ->where('periodo_id', $periodo->id)
                ->whereDate('fecha_matricula', '2025-08-05')
                ->exists(),
            'No se encontró matrícula con fecha 2025-08-05'
        );
    }

    /** @test */
    public function upsertea_actualizacion_de_matricula_si_ya_existe_registro_para_el_mismo_periodo()
    {
        $epSede  = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();

        // Usuario + expediente previos
        $u = User::factory()->create(['username' => 'maria']);
        $exp = $u->expedientesAcademicos()->create([
            'ep_sede_id'        => $epSede->id,
            'codigo_estudiante' => '20201234',
            'estado'            => 'ACTIVO',
            'rol'               => 'ESTUDIANTE',
            'ciclo'             => '3',
        ]);

        // Matrícula inicial
        $mat = Matricula::create([
            'expediente_id'     => $exp->id,
            'periodo_id'        => $periodo->id,
            'ciclo'             => 3,
            'grupo'             => 'A1',
            'modalidad_estudio' => 'PRESENCIAL',
            'modo_contrato'     => 'REGULAR',
            'fecha_matricula'   => '2025-03-15',
        ]);

        $import = new MatriculaRegistroImport($epSede->id, $periodo->id);

        // Mismo expediente/periodo pero con cambios (debería UPDATE, no CREATE)
        $rows = new Collection([[
            'apellidos y nombres' => 'GARCIA, MARIA',
            'usuario'             => 'maria',
            'codigo estudiante'   => '20201234',
            'ciclo'               => '4',
            'grupo'               => 'A2',
            'modalidad estudio'   => 'Virtual',
            'modo contrato'       => 'Convenio',
            'fecha de matrícula'  => '2025-04-01',
        ]]);

        $import->collection($rows);
        $summary = $import->summary();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(0, $summary['created_users']);
        $this->assertSame(0, $summary['created_matriculas']);
        $this->assertSame(1, $summary['updated_matriculas']);

        $this->assertDatabaseHas('matriculas', [
            'id'                => $mat->id, // mismo registro
            'expediente_id'     => $exp->id,
            'periodo_id'        => $periodo->id,
            'ciclo'             => 4,
            'grupo'             => 'A2',
            'modalidad_estudio' => 'VIRTUAL',
            'modo_contrato'     => 'CONVENIO',
        ]);

        $this->assertTrue(
            Matricula::where('id', $mat->id)->whereDate('fecha_matricula', '2025-04-01')->exists()
        );
    }

    /** @test */
    public function anula_matricula_si_viene_sin_fecha_y_ya_existia_registro()
    {
        $epSede  = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();

        $u = User::factory()->create(['username' => 'carlos']);
        $exp = $u->expedientesAcademicos()->create([
            'ep_sede_id'        => $epSede->id,
            'codigo_estudiante' => '20207777',
            'estado'            => 'ACTIVO',
            'rol'               => 'ESTUDIANTE',
            'ciclo'             => '5',
        ]);

        $mat = Matricula::create([
            'expediente_id'     => $exp->id,
            'periodo_id'        => $periodo->id,
            'ciclo'             => 5,
            'grupo'             => 'B1',
            'modalidad_estudio' => 'PRESENCIAL',
            'modo_contrato'     => 'REGULAR',
            'fecha_matricula'   => '2025-01-10',
        ]);

        $import = new MatriculaRegistroImport($epSede->id, $periodo->id);

        // Misma matrícula pero sin fecha -> debe "anular" (fecha null) y contar updated_matriculas
        $rows = new Collection([[
            'apellidos y nombres' => 'PEREZ, CARLOS',
            'usuario'             => 'carlos',
            'codigo estudiante'   => '20207777',
            'ciclo'               => '5',
            'grupo'               => 'B1',
            'modalidad estudio'   => 'Presencial',
            'modo contrato'       => 'Regular',
            'fecha de matrícula'  => '', // sin fecha
        ]]);

        $import->collection($rows);
        $summary = $import->summary();

        $this->assertSame(1, $summary['processed']);
        $this->assertSame(1, $summary['updated_matriculas']);
        $this->assertSame(0, $summary['created_matriculas']);

        $this->assertDatabaseHas('matriculas', [
            'id'                => $mat->id,
            'expediente_id'     => $exp->id,
            'periodo_id'        => $periodo->id,
            'modalidad_estudio' => 'PRESENCIAL',
            'modo_contrato'     => 'REGULAR',
            'fecha_matricula'   => null, // anulada
        ]);
    }

    /** @test */
    public function elige_usuario_existente_por_documento_y_parsea_fechas_y_encabezados_raros()
    {
        $epSede  = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();

        // ya existe usuario por documento, pero SIN username/email coincidente
        $u = User::factory()->create([
            'doc_numero' => '99887766',
            'username'   => 'otro_user',
            'email'      => 'otro@example.com',
            'password'   => '', // obliga ensurePassword
        ]);

        $import = new MatriculaRegistroImport($epSede->id, $periodo->id);

        // Introducimos headers con NBSP y “a. m.” y fecha excel serial (ej. 45500 ≈ 2024-08-18)
        $rows = new Collection([[
            "APELLIDOS\xC2\xA0Y\xC2\xA0NOMBRES" => 'QUISPE CONDORI, ABEL',
            'DNI'                 => '99887766',
            'Correo personal'     => 'abel@example.com',
            'Usuario'             => 'abelq',
            'CÓDIGO ESTUDIANTE'   => '20203333',
            'CICLO'               => '1',
            'GRUPO'               => 'C1',
            'Modalidad Estudio'   => 'SemiPresencial',
            'Modo Contrato'       => 'Beca',
            'Fecha de matrícula'  => '18/08/2024 10:15 a. m.',
            'País'                => 'Bolivia',
        ]]);

        $import->collection($rows);
        $summary = $import->summary();

        // Se debe usar el usuario existente (no crear nuevo)
        $this->assertSame(0, $summary['created_users']);
        $this->assertSame(1, $summary['updated_users']);
        $this->assertSame(1, $summary['created_expedientes']);
        $this->assertSame(1, $summary['created_matriculas']);

        // Matrícula normalizada
        $this->assertDatabaseHas('matriculas', [
            'periodo_id'        => $periodo->id,
            'modalidad_estudio' => 'SEMIPRESENCIAL',
            'modo_contrato'     => 'BECA',
            'ciclo'             => 1,
            'grupo'             => 'C1',
        ]);

        $this->assertTrue(
            Matricula::where('periodo_id', $periodo->id)->whereDate('fecha_matricula', '2024-08-18')->exists()
        );
    }

    /** @test */
    public function idempotencia_mismo_row_no_duplica_por_unique_y_cuenta_update_en_segunda_pasada()
    {
        $epSede  = EpSede::factory()->create();
        $periodo = PeriodoAcademico::factory()->create();

        $import = new MatriculaRegistroImport($epSede->id, $periodo->id);

        $row = [
            'apellidos y nombres' => 'SALAZAR, EVA',
            'dni'                 => '55443322',
            'usuario'             => 'esalazar',
            'codigo estudiante'   => '20201111',
            'ciclo'               => '2',
            'grupo'               => 'D1',
            'modalidad estudio'   => 'Presencial',
            'modo contrato'       => 'Regular',
            'fecha de matrícula'  => '2025-02-01',
        ];

        // 1ra corrida -> crea todo
        $import->collection(new Collection([$row]));
        $s1 = $import->summary();

        $this->assertSame(1, $s1['processed']);
        $this->assertSame(1, $s1['created_users']);
        $this->assertSame(1, $s1['created_expedientes']);
        $this->assertSame(1, $s1['created_matriculas']);
        $this->assertSame(0, $s1['updated_matriculas']);

        // 2da corrida con el mismo row -> no debe crear otra matrícula (unique), cuenta como update
        $import2 = new MatriculaRegistroImport($epSede->id, $periodo->id);
        $import2->collection(new Collection([$row]));
        $s2 = $import2->summary();

        $this->assertSame(1, $s2['processed']);
        $this->assertSame(0, $s2['created_users']);       // ya existe
        $this->assertSame(0, $s2['created_expedientes']); // ya existe
        $this->assertSame(0, $s2['created_matriculas']);  // no duplica
        $this->assertSame(1, $s2['updated_matriculas']);  // upsert actualiza
    }
}
