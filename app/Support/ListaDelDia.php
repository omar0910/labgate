<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Horario;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cómo quedó cada alumno en una clase un día: la lista de consulta de "Gestión
 * de Clases" (encargado) y de "Clases de hoy" (administrador).
 *
 * Antes quien no tenía registro salía siempre como "Falta", aunque la clase
 * siguiera en curso (todavía podía registrarse), aunque el profesor no la
 * hubiera dado, o aunque ese día nadie quedara registrado (lista en papel).
 * Aquí se sigue la misma regla que "Mi Progreso" del alumno
 * (App\Support\FaltasSinRegistro): sólo es falta si la clase ya terminó, se dio,
 * otros sí quedaron registrados y él ya estaba inscrito.
 */
class ListaDelDia
{
    /** Cómo se muestra cada estado ('clase' es el color de partials/estilos-progreso). */
    const ESTADOS = [
        'presente' => ['texto' => 'Presente', 'icono' => 'bi-check-lg', 'clase' => 'asistio'],
        'justificado' => ['texto' => 'Justificado', 'icono' => 'bi-file-earmark-text', 'clase' => 'justificada'],
        'falta' => ['texto' => 'Falta', 'icono' => 'bi-x-lg', 'clase' => 'falta'],
        'sin_registro' => ['texto' => 'Falta (no se registró)', 'icono' => 'bi-x-circle', 'clase' => 'sin-registro'],
        'pendiente' => ['texto' => 'Aún no se registra', 'icono' => 'bi-hourglass-split', 'clase' => 'no-impartida'],
        'no_impartida' => ['texto' => 'No hubo clase', 'icono' => 'bi-calendar-x', 'clase' => 'no-impartida'],
        'sin_lista' => ['texto' => 'Sin registro', 'icono' => 'bi-dash-circle', 'clase' => 'no-impartida'],
        'no_inscrito' => ['texto' => 'Aún no estaba inscrito', 'icono' => 'bi-person-dash', 'clase' => 'no-impartida'],
    ];

    /**
     * @return array{alumnos: Collection<int, array{alumno: \App\Models\User, registro: ?Asistencia, estado: string}>, conteos: array, situacion: ?string, estadoProfesor: ?string}
     */
    public static function de(Horario $horario, string $fecha): array
    {
        $registros = Asistencia::where('horario_id', $horario->id)
            ->whereDate('fecha', $fecha)
            ->where('tipo', 'Clase')
            ->get()
            ->keyBy('user_id');

        $estadoProfesor = AsistenciaProfesor::where('horario_id', $horario->id)
            ->whereDate('fecha', $fecha)
            ->value('estado');

        $noSeDio = in_array($estadoProfesor, ['falta', 'justificado'], true);
        $hoy = Carbon::today()->toDateString();
        $yaTermino = $fecha < $hoy || ($fecha === $hoy && Carbon::now()->format('H:i:s') > $horario->hora_fin);

        // Por apellidos (antes, en unas pantallas por nombre y en otras sólo por el paterno)
        $alumnos = ($horario->grupo ? $horario->grupo->alumnos()->get() : collect())
            ->sortBy([['apellido_paterno', 'asc'], ['apellido_materno', 'asc'], ['name', 'asc']])
            ->values()
            ->map(function ($alumno) use ($registros, $noSeDio, $yaTermino, $fecha) {
                $registro = $registros->get($alumno->id);
                $desde = $alumno->pivot->created_at ? Carbon::parse($alumno->pivot->created_at)->toDateString() : null;

                $estado = match (true) {
                    (bool) $registro => $registro->estado,
                    $noSeDio => 'no_impartida',
                    ! $yaTermino => 'pendiente',
                    $desde && $fecha < $desde => 'no_inscrito',
                    $registros->isEmpty() => 'sin_lista',
                    default => 'sin_registro',
                };

                return ['alumno' => $alumno, 'registro' => $registro, 'estado' => $estado];
            });

        $cuantos = $alumnos->countBy('estado');

        // Qué pasó con la clase ese día, para explicarlo arriba de la lista
        $situacion = match (true) {
            $noSeDio => 'no_impartida',
            ! $yaTermino => $fecha > $hoy ? 'futura' : 'en_curso',
            $registros->isEmpty() => 'sin_lista',
            default => null,
        };

        return [
            'alumnos' => $alumnos,
            'conteos' => [
                'presentes' => $cuantos['presente'] ?? 0,
                'justificados' => $cuantos['justificado'] ?? 0,
                'faltas' => ($cuantos['falta'] ?? 0) + ($cuantos['sin_registro'] ?? 0),
                'pendientes' => ($cuantos['pendiente'] ?? 0),
                'total' => $alumnos->count(),
            ],
            'situacion' => $situacion,
            'estadoProfesor' => $estadoProfesor,
        ];
    }

    public static function estado(string $estado): array
    {
        return self::ESTADOS[$estado] ?? ['texto' => ucfirst($estado), 'icono' => 'bi-circle', 'clase' => 'no-impartida'];
    }
}
