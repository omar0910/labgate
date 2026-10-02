<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Horario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Las clases que cuentan en "Mi Progreso" de un alumno, una por una.
 *
 * Son sus registros de clase del semestre (asistió, justificada o falta) más las
 * clases en las que no quedó registrado y sus compañeros sí (FaltasSinRegistro).
 * La tarjeta de cada materia y su pantalla de detalle salen de aquí, para que
 * digan siempre los mismos números.
 */
class ProgresoDelAlumno
{
    /** Porcentaje mínimo para estar "Regular" en una materia. */
    const MINIMO = 80;

    /**
     * Cómo se muestra cada clase. 'grupo' es el filtro del detalle al que
     * pertenece; 'clase' el color (estilos en partials/estilos-progreso).
     */
    const ESTADOS = [
        'presente' => ['texto' => 'Asistió', 'icono' => 'bi-check-circle-fill', 'clase' => 'asistio', 'grupo' => 'asistencias'],
        'justificado' => ['texto' => 'Justificada', 'icono' => 'bi-file-earmark-medical-fill', 'clase' => 'justificada', 'grupo' => 'justificadas'],
        'falta' => ['texto' => 'Falta', 'icono' => 'bi-x-circle-fill', 'clase' => 'falta', 'grupo' => 'faltas'],
        'sin_registro' => ['texto' => 'No te registraste', 'icono' => 'bi-x-circle', 'clase' => 'sin-registro', 'grupo' => 'faltas'],
        'no_impartida' => ['texto' => 'No hubo clase', 'icono' => 'bi-calendar-x', 'clase' => 'no-impartida', 'grupo' => 'no_hubo'],
        'no_impartida_justificada' => ['texto' => 'No hubo clase', 'icono' => 'bi-calendar-x', 'clase' => 'no-impartida', 'grupo' => 'no_hubo'],
    ];

    /** Para estados que no estén en la lista (no debería haber). */
    const ESTADO_OTRO = ['texto' => 'Registrada', 'icono' => 'bi-circle-fill', 'clase' => 'no-impartida', 'grupo' => 'otros'];

    public static function estado(string $estado): array
    {
        return self::ESTADOS[$estado] ?? ['texto' => ucfirst($estado)] + self::ESTADO_OTRO;
    }

    /**
     * @return Collection<int, array{fecha: string, horario: ?Horario, materia_id: ?int, materia: string, estado: string, asistencia: ?Asistencia}>
     */
    public static function clases(User $alumno, ?int $semestreId, ?int $materiaId = null): Collection
    {
        $registros = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Clase')
            ->with(['horario.materia'])
            ->when($semestreId, fn($q) => $q->whereHas('horario', fn($h) => $h->where('semestre_id', $semestreId)))
            ->when($materiaId, fn($q) => $q->whereHas('horario', fn($h) => $h->where('materia_id', $materiaId)))
            ->get()
            ->map(fn($asistencia) => self::clase($asistencia->horario, $asistencia->fecha, $asistencia->estado, $asistencia));

        $sinRegistro = FaltasSinRegistro::de($alumno, $semestreId)
            ->when($materiaId, fn($faltas) => $faltas->filter(fn($falta) => $falta['horario']->materia_id == $materiaId))
            ->map(fn($falta) => self::clase($falta['horario'], $falta['fecha'], 'sin_registro'));

        return self::ordenar($registros->concat($sinRegistro));
    }

    /**
     * Las clases que no se dieron (el profesor faltó o justificó) y en las que el
     * alumno no tiene registro. No cuentan para el porcentaje; el detalle las
     * muestra para que sepa por qué ese día no aparece como falta.
     */
    public static function noImpartidas(User $alumno, Collection $horarios): Collection
    {
        if ($horarios->isEmpty()) {
            return collect();
        }

        $horarios = $horarios->keyBy('id');

        $propias = Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Clase')
            ->whereIn('horario_id', $horarios->keys())
            ->get(['horario_id', 'fecha'])
            ->mapWithKeys(fn($a) => [$a->horario_id . '|' . substr((string) $a->fecha, 0, 10) => true]);

        // Nada cuenta antes de que lo inscribieran al grupo (si se sabe cuándo)
        $inscritoDesde = $alumno->grupos()->get()->mapWithKeys(fn($g) => [
            $g->id => $g->pivot->created_at ? Carbon::parse($g->pivot->created_at)->toDateString() : null,
        ]);

        return self::ordenar(AsistenciaProfesor::whereIn('horario_id', $horarios->keys())
            ->whereIn('estado', ['falta', 'justificado'])
            ->whereDate('fecha', '<=', Carbon::today()->toDateString())
            ->get()
            ->reject(function ($profesor) use ($propias, $horarios, $inscritoDesde) {
                $dia = substr((string) $profesor->fecha, 0, 10);
                $desde = $inscritoDesde[$horarios[$profesor->horario_id]->grupo_id] ?? null;

                return isset($propias[$profesor->horario_id . '|' . $dia]) || ($desde && $dia < $desde);
            })
            ->map(fn($profesor) => self::clase(
                $horarios[$profesor->horario_id],
                $profesor->fecha,
                $profesor->estado === 'justificado' ? 'no_impartida_justificada' : 'no_impartida'
            )));
    }

    /**
     * Los números de un grupo de clases: los de la tarjeta, el encabezado del
     * detalle y el resumen general del semestre.
     */
    public static function resumen(Collection $clases): array
    {
        $total = $clases->count();
        $asistencias = $clases->where('estado', 'presente')->count();
        $justificadas = $clases->where('estado', 'justificado')->count();
        $sinRegistro = $clases->where('estado', 'sin_registro')->count();
        $faltas = $clases->where('estado', 'falta')->count() + $sinRegistro;

        // El porcentaje de cumplimiento suma presentes + justificadas
        $porcentaje = $total > 0 ? round((($asistencias + $justificadas) / $total) * 100) : 0;

        // Si está por debajo del mínimo: cuántas clases seguidas tendría que
        // asistir para volver a alcanzarlo, (a + n) / (t + n) >= MINIMO.
        $paraRecuperar = $total > 0 && $porcentaje < self::MINIMO
            ? max(1, (int) ceil((self::MINIMO * $total - 100 * ($asistencias + $justificadas)) / (100 - self::MINIMO)))
            : 0;

        return [
            'total' => $total,
            'asistencias' => $asistencias,
            'justificadas' => $justificadas,
            'faltas' => $faltas,
            'sin_registro' => $sinRegistro,
            'porcentaje' => $porcentaje,
            'para_recuperar' => $paraRecuperar,
        ];
    }

    protected static function clase(?Horario $horario, $fecha, string $estado, ?Asistencia $asistencia = null): array
    {
        return [
            'fecha' => substr((string) $fecha, 0, 10),
            'horario' => $horario,
            'materia_id' => $horario?->materia_id,
            'materia' => $horario?->materia?->nombre_materia ?? 'Materia Eliminada o Sin Asignar',
            'estado' => $estado,
            'asistencia' => $asistencia,
        ];
    }

    /** De la más reciente a la más antigua. */
    public static function ordenar(Collection $clases): Collection
    {
        return $clases->sortByDesc(fn($clase) => $clase['fecha'] . ' ' . ($clase['horario']->hora_inicio ?? ''))->values();
    }
}
