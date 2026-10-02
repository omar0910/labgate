<?php

namespace App\Demo;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\CentroComputo;
use App\Models\DiaInhabil;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Semestre;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Hace que la demostración tenga vida: pone lo que "está pasando" hoy.
 *
 * Cada pocos minutos revisa qué clases ya empezaron y registra a sus alumnos, y
 * abre y cierra sesiones de uso libre en las horas en que el laboratorio no
 * tiene clase. Así el Monitor en vivo, los paneles y los reportes del día tienen
 * algo que mostrar a quien entre a cualquier hora.
 *
 * Deja libres a las cuentas de demostración: no registra al alumno mientras su
 * clase está en curso ni las clases de su profesor, para que quien entre a
 * probar el sistema pueda hacerlo él mismo.
 */
class Simulador
{
    /** Minutos que se le dan a una clase antes de darla por empezada. */
    const MARGEN = 3;

    public static function avanzar(?Carbon $ahora = null): array
    {
        $ahora = $ahora ?: now();
        $hoy = $ahora->copy()->startOfDay();
        $hecho = ['clases' => 0, 'registros' => 0, 'uso_libre_abierto' => 0, 'uso_libre_cerrado' => 0];

        $semestre = Semestre::activo();
        $hayClases = $semestre
            && $semestre->contieneFecha($hoy)
            && $hoy->dayOfWeekIso <= 5
            && ! DiaInhabil::whereDate('fecha', $hoy)->exists();

        if (! $hayClases) {
            $hecho['uso_libre_cerrado'] = self::cerrarUsoLibre(null, $ahora);

            return $hecho;
        }

        $alumnoDemo = User::where('username', config('demo.cuentas.Alumno'))->value('id');
        $profesorDemo = User::where('username', config('demo.cuentas.Profesor'))->value('id');
        $horarios = Horario::with('centroComputo')->where('semestre_id', $semestre->id)->queTocanEl($hoy)->get();

        foreach ($horarios as $horario) {
            $inicio = Carbon::parse($hoy->toDateString() . ' ' . $horario->hora_inicio);
            $fin = Carbon::parse($hoy->toDateString() . ' ' . $horario->hora_fin);

            if ($ahora->lt($inicio->copy()->addMinutes(self::MARGEN))) {
                continue;
            }

            $enCurso = $ahora->lt($fin);
            $yaRegistrada = Asistencia::where('horario_id', $horario->id)->whereDate('fecha', $hoy)->exists()
                || AsistenciaProfesor::where('horario_id', $horario->id)->whereDate('fecha', $hoy)->exists();

            if (! $yaRegistrada) {
                // Al empezar la clase, quien estaba en uso libre deja el laboratorio
                $hecho['uso_libre_cerrado'] += self::cerrarUsoLibre($horario->centro_computo_id, $inicio);
                $hecho['registros'] += self::registrarClase($horario, $hoy, $enCurso, $profesorDemo, $enCurso ? [$alumnoDemo] : []);
                $hecho['clases']++;
            }

            if (! $enCurso) {
                self::terminarClase($horario, $hoy, $fin, $alumnoDemo);
            }
        }

        foreach (CentroComputo::where('permite_uso_libre', true)->get() as $laboratorio) {
            $enClase = $horarios->contains(fn($h) => $h->centro_computo_id === $laboratorio->id
                && $ahora->gte(Carbon::parse($hoy->toDateString() . ' ' . $h->hora_inicio)->subMinutes(10))
                && $ahora->lt(Carbon::parse($hoy->toDateString() . ' ' . $h->hora_fin)));

            $abierto = (int) $ahora->format('G') >= 7 && (int) $ahora->format('Gi') < 2045;

            if ($enClase || ! $abierto) {
                $hecho['uso_libre_cerrado'] += self::cerrarUsoLibre($laboratorio->id, $ahora);
                continue;
            }

            [$abiertas, $cerradas] = self::moverUsoLibre($laboratorio, $ahora, $alumnoDemo);
            $hecho['uso_libre_abierto'] += $abiertas;
            $hecho['uso_libre_cerrado'] += $cerradas;
        }

        return $hecho;
    }

    /** Registra a los alumnos que llegaron y, casi siempre, el registro del profesor. */
    protected static function registrarClase(Horario $horario, Carbon $hoy, bool $enCurso, ?int $profesorDemo, array $omitir): int
    {
        // Las clases del profesor de demostración se quedan sin su registro, para
        // que quien entre con esa cuenta pueda pasar lista él mismo.
        $azar = mt_rand(0, 99);
        $estado = $horario->user_id === $profesorDemo ? null : ($azar >= 97 ? 'falta' : ($azar >= 94 ? 'justificado' : ($azar >= 92 ? null : 'asistio')));

        if ($estado) {
            AsistenciaProfesor::create([
                'horario_id' => $horario->id, 'fecha' => $hoy->toDateString(), 'estado' => $estado,
                'observaciones' => $estado === 'justificado' ? Catalogo::MOTIVOS_DEL_PROFESOR[mt_rand(0, 2)] : null,
            ]);
        }

        if (in_array($estado, ['falta', 'justificado'], true)) {
            return 0;
        }

        $miembros = DB::table('alumno_grupo')->join('users', 'users.id', '=', 'alumno_grupo.user_id')
            ->where('grupo_id', $horario->grupo_id)->where('users.activo', true)
            ->orderBy('users.id')->pluck('users.id')->map(fn($id) => (int) $id)->all();

        $sinPc = Equipo::where('centro_computo_id', $horario->centro_computo_id)->where('estado', '!=', 'disponible')
            ->pluck('numero_maquina')->map(fn($n) => (int) $n)->all();

        $filas = Sesion::filas($horario, $hoy, $miembros, (int) ($horario->centroComputo->capacidad ?? 30), $estado, $enCurso, $sinPc, array_filter($omitir));

        foreach ($filas as $fila) {
            // Con el modelo, para que cada registro le sume su uso a la computadora
            Asistencia::create($fila);
        }

        return count($filas);
    }

    /** Al terminar la clase: se cierran sus registros y se anota al alumno de demostración si nadie lo hizo. */
    protected static function terminarClase(Horario $horario, Carbon $hoy, Carbon $fin, ?int $alumnoDemo): void
    {
        Asistencia::where('horario_id', $horario->id)->whereDate('fecha', $hoy)->where('tipo', 'Clase')
            ->where('estado', 'presente')->whereNull('fecha_hora_salida')
            ->update(['fecha_hora_salida' => $fin->toDateTimeString()]);

        if (! $alumnoDemo || ! DB::table('alumno_grupo')->where('grupo_id', $horario->grupo_id)->where('user_id', $alumnoDemo)->exists()) {
            return;
        }

        $huboClase = Asistencia::where('horario_id', $horario->id)->whereDate('fecha', $hoy)->where('estado', 'presente')->exists();
        $suRegistro = Asistencia::where('horario_id', $horario->id)->whereDate('fecha', $hoy)->where('user_id', $alumnoDemo)->exists();

        if ($huboClase && ! $suRegistro) {
            Asistencia::create([
                'horario_id' => $horario->id, 'user_id' => $alumnoDemo, 'fecha' => $hoy->toDateString(), 'tipo' => 'Clase',
                'estado' => 'presente', 'centro_computo_id' => $horario->centro_computo_id, 'numero_maquina' => 1,
                'fecha_hora_registro' => Carbon::parse($hoy->toDateString() . ' ' . $horario->hora_inicio)->addMinutes(4)->toDateTimeString(),
                'fecha_hora_salida' => $fin->toDateTimeString(),
            ]);
        }
    }

    /** Cierra el uso libre abierto de hoy, de un laboratorio o de todos. Devuelve cuántos cerró. */
    protected static function cerrarUsoLibre(?int $laboratorioId, Carbon $salida): int
    {
        return Asistencia::where('tipo', 'Uso Libre')->whereDate('fecha', $salida->toDateString())->whereNull('fecha_hora_salida')
            ->when($laboratorioId, fn($q) => $q->where('centro_computo_id', $laboratorioId))
            ->update(['fecha_hora_salida' => $salida->toDateTimeString()]);
    }

    /** En un laboratorio sin clase: se van algunos de los que estaban y llegan otros. */
    protected static function moverUsoLibre(CentroComputo $laboratorio, Carbon $ahora, ?int $alumnoDemo): array
    {
        $cerradas = 0;
        $abiertas = Asistencia::where('tipo', 'Uso Libre')->where('centro_computo_id', $laboratorio->id)
            ->whereDate('fecha', $ahora->toDateString())->whereNull('fecha_hora_salida')->get();

        foreach ($abiertas as $sesion) {
            // La que abrió el visitante con la cuenta de demostración no se le cierra
            if ($sesion->user_id === $alumnoDemo) {
                continue;
            }

            $minutos = Carbon::parse($sesion->fecha_hora_registro)->diffInMinutes($ahora);
            if ($minutos > 110 || ($minutos > 30 && mt_rand(0, 99) < 18)) {
                $sesion->update(['fecha_hora_salida' => $ahora->toDateTimeString()]);
                $cerradas++;
            }
        }

        // Entre 3 y 7 personas a la vez, según la hora
        $meta = 3 + crc32($ahora->format('Y-m-d H') . $laboratorio->id) % 5;
        $quedan = $abiertas->count() - $cerradas;
        $nuevas = 0;

        for ($intento = 0; $intento < 6 && $quedan + $nuevas < $meta; $intento++) {
            if (mt_rand(0, 99) >= 65) {
                continue;
            }

            $alumno = User::where('rol', 'Alumno')->where('activo', true)->where('id', '!=', $alumnoDemo ?? 0)
                ->whereDoesntHave('asistencias', fn($q) => $q->whereDate('fecha', $ahora->toDateString())->whereNull('fecha_hora_salida'))
                ->inRandomOrder()->first();

            $pc = Equipo::where('centro_computo_id', $laboratorio->id)->where('estado', 'disponible')->inRandomOrder()->value('numero_maquina');

            if (! $alumno || ! $pc || Asistencia::estaOcupada($laboratorio->id, $pc)) {
                continue;
            }

            Asistencia::create([
                'user_id' => $alumno->id, 'tipo' => 'Uso Libre', 'estado' => 'presente', 'centro_computo_id' => $laboratorio->id,
                'numero_maquina' => $pc, 'fecha' => $ahora->toDateString(),
                'fecha_hora_registro' => $ahora->copy()->subMinutes(mt_rand(0, 4))->toDateTimeString(),
            ]);
            $nuevas++;
        }

        return [$nuevas, $cerradas];
    }
}
