<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * La asistencia de cada alumno en una clase de un profesor (una materia con un
 * grupo en un semestre, con todas sus sesiones de la semana).
 *
 * Cuenta igual que "Mi Progreso" del alumno (App\Support\ProgresoDelAlumno): sus
 * registros, más las clases en que sus compañeros sí quedaron registrados y él
 * no. Esas no cuentan si el profesor no dio la clase (falta o justificado), si
 * todavía no estaba inscrito en el grupo o si es la de hoy y aún no termina.
 *
 * Antes cada día con algún registro contaba para todos: al alumno inscrito tarde
 * le salían faltas de clases de antes de inscribirse, y el profesor veía un
 * porcentaje distinto del que veía el alumno.
 */
class EstadisticasDeClase
{
    /**
     * @return array{horarios: Collection, totalClases: int, alumnos: array, resumen: array}
     */
    public static function de(Horario $horario): array
    {
        // Todas sus sesiones, fijas y reservas especiales (el alumno también las ve juntas)
        $horarios = Horario::where('user_id', $horario->user_id)
            ->where('materia_id', $horario->materia_id)
            ->where('grupo_id', $horario->grupo_id)
            ->where('semestre_id', $horario->semestre_id)
            ->get()
            ->keyBy('id');
        $ids = $horarios->keys();

        // Sin los dados de baja (la relación ya los quita) y con su fecha de inscripción
        $alumnos = $horario->grupo ? $horario->grupo->alumnos()->get() : collect();

        $registros = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->get(['user_id', 'horario_id', 'fecha', 'estado']);

        $noImpartidas = AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereIn('estado', ['falta', 'justificado'])
            ->get(['horario_id', 'fecha'])
            ->mapWithKeys(fn($r) => [self::llave($r) => true]);

        $hoy = Carbon::today()->toDateString();
        $horaActual = Carbon::now()->format('H:i:s');

        // Las clases que se dieron y en las que se tomó asistencia: alguien quedó
        // registrado y el profesor no faltó.
        $clasesDadas = $registros
            ->filter(fn($r) => substr((string) $r->fecha, 0, 10) <= $hoy)
            ->map(fn($r) => ['llave' => self::llave($r), 'horario_id' => $r->horario_id, 'dia' => substr((string) $r->fecha, 0, 10)])
            ->unique('llave')
            ->reject(fn($c) => isset($noImpartidas[$c['llave']]))
            ->values();

        // A quien no se registró sólo se le cuenta falta cuando la clase ya
        // terminó: la de hoy, mientras dura, todavía puede registrarse.
        $clasesParaFaltas = $clasesDadas
            ->reject(fn($c) => $c['dia'] === $hoy && optional($horarios->get($c['horario_id']))->hora_fin > $horaActual)
            ->values();

        $porAlumno = $registros->groupBy('user_id');
        $datos = [];

        foreach ($alumnos as $alumno) {
            $suyos = $porAlumno->get($alumno->id, collect());
            $llavesSuyas = $suyos->mapWithKeys(fn($r) => [self::llave($r) => true]);
            $desde = $alumno->pivot->created_at ? Carbon::parse($alumno->pivot->created_at)->toDateString() : null;

            $sinRegistro = $clasesParaFaltas
                ->reject(fn($c) => isset($llavesSuyas[$c['llave']]))
                ->reject(fn($c) => $desde && $c['dia'] < $desde)
                ->count();

            $presentes = $suyos->where('estado', 'presente')->count();
            $justificadas = $suyos->where('estado', 'justificado')->count();
            $total = $suyos->count() + $sinRegistro;
            $porcentaje = $total > 0 ? (int) round((($presentes + $justificadas) / $total) * 100) : 0;

            $nombreCompleto = trim(($alumno->apellido_paterno ?? '') . ' ' . ($alumno->apellido_materno ?? '') . ' ' . $alumno->name);

            $datos[] = [
                'id' => $alumno->id,
                'matricula' => $alumno->matricula ?? 'S/M',
                'nombre' => mb_strtoupper($nombreCompleto, 'UTF-8'),
                'nombre_completo' => mb_convert_case(mb_strtolower($nombreCompleto, 'UTF-8'), MB_CASE_TITLE, 'UTF-8'),
                'total' => $total,
                'presentes' => $presentes,
                'justificadas' => $justificadas,
                // Las que cumplen: presente o justificada (así las suman el PDF y el Excel)
                'asistencias' => $presentes + $justificadas,
                'faltas' => $suyos->where('estado', 'falta')->count() + $sinRegistro,
                'sin_registro' => $sinRegistro,
                'porcentaje' => $porcentaje,
                'en_riesgo' => $total > 0 && $porcentaje < ProgresoDelAlumno::MINIMO,
                // Sin clases contadas todavía (recién inscrito) no está en riesgo ni al día
                'estado' => $total === 0 ? 'SIN CLASES' : ($porcentaje < ProgresoDelAlumno::MINIMO ? 'EN RIESGO' : 'REGULAR'),
                '_orden' => mb_strtolower(\Illuminate\Support\Str::ascii($nombreCompleto), 'UTF-8'),
            ];
        }

        // Por apellidos, sin que los acentos cambien el orden
        usort($datos, fn($a, $b) => strcmp($a['_orden'], $b['_orden']));

        $conClases = collect($datos)->where('total', '>', 0);

        return [
            'horarios' => $horarios,
            'totalClases' => $clasesDadas->count(),
            'alumnos' => $datos,
            'resumen' => [
                'alumnos' => count($datos),
                'en_riesgo' => $conClases->where('en_riesgo', true)->count(),
                // Promedio de los porcentajes de quienes ya tienen clases contadas
                'promedio' => $conClases->isNotEmpty() ? (int) round($conClases->avg('porcentaje')) : null,
            ],
        ];
    }

    /** "horario_id|AAAA-MM-DD" */
    protected static function llave($registro): string
    {
        return $registro->horario_id . '|' . substr((string) $registro->fecha, 0, 10);
    }
}
