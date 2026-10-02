<?php

namespace Tests;

use App\Models\Asistencia;
use App\Models\CentroComputo;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Lo mínimo para probar: un semestre en curso, un laboratorio con sus
 * computadoras y una clase que se está dando en este momento (ver TestCase::AHORA).
 */
trait Escenario
{
    /** La contraseña de todos los usuarios que crea el escenario. */
    protected string $contrasena = 'secreto123';

    protected int $consecutivo = 0;

    protected function semestre(): Semestre
    {
        return Semestre::activo() ?? Semestre::create([
            'nombre' => 'Enero - Junio 2026',
            'fecha_inicio' => '2026-01-19',
            'fecha_fin' => '2026-06-12',
            'es_activo' => true,
        ]);
    }

    /** Un laboratorio; al crearlo el sistema le da de alta sus computadoras. */
    protected function laboratorio(string $nombre = 'Laboratorio A', int $computadoras = 10, bool $usoLibre = true, array $extra = []): CentroComputo
    {
        return CentroComputo::create(array_merge([
            'nombre_centro' => $nombre,
            'capacidad' => $computadoras,
            'permite_uso_libre' => $usoLibre,
        ], $extra));
    }

    protected function usuario(string $rol, array $extra = []): User
    {
        $n = ++$this->consecutivo;

        return User::create(array_merge([
            'name' => $rol,
            'apellido_paterno' => 'De Prueba',
            'apellido_materno' => (string) $n,
            'username' => strtolower($rol) . $n,
            'email' => strtolower($rol) . $n . '@pruebas.test',
            'password' => Hash::make($this->contrasena),
            'rol' => $rol,
            'matricula' => $rol === 'Alumno' ? '2631' . str_pad((string) $n, 5, '0', STR_PAD_LEFT) : null,
            'activo' => true,
        ], $extra));
    }

    /**
     * Una clase de los miércoles de 10:00 a 12:00 (en curso a la hora de las
     * pruebas), con su materia, su grupo y su profesor.
     */
    protected function clase(CentroComputo $laboratorio, array $extra = []): Horario
    {
        $n = ++$this->consecutivo;
        $semestre = $this->semestre();

        return Horario::create(array_merge([
            'user_id' => $this->usuario('Profesor')->id,
            'materia_id' => Materia::create(['clave' => 'MAT-' . $n, 'nombre_materia' => 'Materia ' . $n, 'creditos' => '5'])->id,
            'grupo_id' => Grupo::create(['nombre_grupo' => '3SM-MATERIA ' . $n, 'semestre_id' => $semestre->id])->id,
            'centro_computo_id' => $laboratorio->id,
            'dia_semana' => 'Miércoles',
            'hora_inicio' => '10:00',
            'hora_fin' => '12:00',
            'tipo_reserva' => 'recurrente',
            'semestre_id' => $semestre->id,
        ], $extra));
    }

    /** Un alumno inscrito en el grupo de esa clase desde el inicio del semestre. */
    protected function alumnoDe(Horario $clase): User
    {
        $alumno = $this->usuario('Alumno');

        $alumno->grupos()->attach($clase->grupo_id, ['created_at' => '2026-01-19 08:00:00', 'updated_at' => '2026-01-19 08:00:00']);

        return $alumno;
    }

    /**
     * Como si la petición saliera de una computadora del laboratorio: el script
     * instalado en ella abre el sistema identificándose, y eso queda en la sesión.
     */
    protected function desdeLaPc(CentroComputo $laboratorio, int $numero): array
    {
        return ['equipo_del_laboratorio' => ['centro' => $laboratorio->id, 'maquina' => $numero]];
    }

    /** El alumno ya está registrado en su clase de hoy, en esa computadora. */
    protected function registradoEnClase(User $alumno, Horario $clase, ?int $pc, array $extra = []): Asistencia
    {
        return Asistencia::create(array_merge([
            'user_id' => $alumno->id,
            'horario_id' => $clase->id,
            'tipo' => 'Clase',
            'estado' => 'presente',
            'numero_maquina' => $pc,
            'centro_computo_id' => $clase->centro_computo_id,
            'fecha' => now()->toDateString(),
            'fecha_hora_registro' => now()->copy()->subMinutes(20),
        ], $extra));
    }

    protected function enUsoLibre(User $alumno, CentroComputo $laboratorio, int $pc, array $extra = []): Asistencia
    {
        return Asistencia::create(array_merge([
            'user_id' => $alumno->id,
            'tipo' => 'Uso Libre',
            'estado' => 'presente',
            'numero_maquina' => $pc,
            'centro_computo_id' => $laboratorio->id,
            'fecha' => now()->toDateString(),
            'fecha_hora_registro' => now()->copy()->subMinutes(20),
        ], $extra));
    }
}
