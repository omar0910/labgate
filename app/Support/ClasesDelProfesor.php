<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\DiaInhabil;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Semestre;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Las clases que un profesor ya dio en un semestre, una por fecha, con cómo
 * quedó su lista. Es lo que muestra su Historial.
 *
 * Antes el Historial pedía escoger una fecha y mostraba las clases de ese día:
 * para saber qué listas faltaban había que ir día por día. Aquí salen todas,
 * con su estado a la vista.
 *
 * Las fechas son las de CalendarioDeClases (las mismas que usan los reportes y
 * las clases pendientes del administrador): sin días inhábiles y desde que la
 * clase existe en el sistema.
 */
class ClasesDelProfesor
{
    /** Cómo quedó la lista de cada clase. */
    const ESTADOS = [
        'sin_lista' => ['texto' => 'Sin lista', 'icono' => 'bi-exclamation-circle-fill', 'clase' => 'falta'],
        'parcial' => ['texto' => 'Lista incompleta', 'icono' => 'bi-hourglass-split', 'clase' => 'justificada'],
        'completa' => ['texto' => 'Lista completa', 'icono' => 'bi-check-circle-fill', 'clase' => 'asistio'],
        'no_impartida' => ['texto' => 'No impartida', 'icono' => 'bi-calendar-x', 'clase' => 'no-impartida'],
    ];

    /**
     * Las clases que ya pasaron (la de hoy, cuando ya terminó o ya tiene
     * registros), de la más reciente a la más antigua. Con $dia, sólo las de ese
     * día, sin importar la hora (así se puede abrir la de hoy desde el Historial).
     *
     * @return Collection<int, array{llave: string, horario: Horario, fecha: string, estado: string, alumnos: int, registrados: int, presentes: int, faltas: int, justificadas: int, profesor: ?string}>
     */
    public static function pasadas(int $profesorId, Semestre $semestre, ?string $dia = null): Collection
    {
        $horarios = Horario::with(['materia', 'grupo', 'centroComputo'])
            ->where('user_id', $profesorId)
            ->where('semestre_id', $semestre->id)
            ->get();

        if ($horarios->isEmpty()) {
            return collect();
        }

        $hoy = Carbon::today()->toDateString();
        $desde = $dia ?? CalendarioDeClases::soloFecha($semestre->fecha_inicio);
        $hasta = $dia ?? min($hoy, CalendarioDeClases::soloFecha($semestre->fecha_fin));

        if ($desde > $hasta) {
            return collect();
        }

        $ids = $horarios->pluck('id');
        $inhabiles = DiaInhabil::where('semestre_id', $semestre->id)->pluck('fecha')->all();

        // Cuántos alumnos quedaron en cada estado, por clase y día
        $conteos = [];
        Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->select('horario_id', 'fecha', 'estado', DB::raw('COUNT(*) as total'))
            ->groupBy('horario_id', 'fecha', 'estado')
            ->get()
            ->each(function ($fila) use (&$conteos) {
                $conteos[$fila->horario_id . '|' . CalendarioDeClases::soloFecha($fila->fecha)][$fila->estado] = (int) $fila->total;
            });

        // Si el profesor está registrado como falta o justificado, la clase no se dio
        $profesor = AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->get(['horario_id', 'fecha', 'estado'])
            ->mapWithKeys(fn($r) => [$r->horario_id . '|' . CalendarioDeClases::soloFecha($r->fecha) => $r->estado]);

        // Alumnos de cada grupo (sin los dados de baja)
        $alumnosPorGrupo = Grupo::whereIn('id', $horarios->pluck('grupo_id')->filter())
            ->withCount('alumnos')
            ->pluck('alumnos_count', 'id');

        $horaActual = Carbon::now()->format('H:i:s');
        $clases = collect();

        foreach ($horarios as $horario) {
            $vigenteDesde = CalendarioDeClases::vigenteDesde($horario, $semestre)->format('Y-m-d');

            foreach (CalendarioDeClases::fechas($horario, $desde, $hasta, $inhabiles)['clases'] as $fecha) {
                $llave = $horario->id . '|' . $fecha;
                $conteo = $conteos[$llave] ?? [];
                $registrados = array_sum($conteo);
                $estadoProfesor = $profesor[$llave] ?? null;

                if (! $dia) {
                    // Antes de que la clase existiera en el sistema, y sin nada capturado
                    if ($fecha < $vigenteDesde && ! $registrados && ! $estadoProfesor) {
                        continue;
                    }

                    // La de hoy, cuando ya terminó o cuando ya tiene registros
                    if ($fecha === $hoy && ! $registrados && $horario->hora_fin > $horaActual) {
                        continue;
                    }
                }

                $alumnos = (int) ($alumnosPorGrupo[$horario->grupo_id] ?? 0);

                $estado = match (true) {
                    in_array($estadoProfesor, ['falta', 'justificado'], true) => 'no_impartida',
                    $registrados === 0 => 'sin_lista',
                    $registrados < $alumnos => 'parcial',
                    default => 'completa',
                };

                $clases->push([
                    'llave' => $llave,
                    'horario' => $horario,
                    'fecha' => $fecha,
                    'estado' => $estado,
                    'alumnos' => $alumnos,
                    'registrados' => $registrados,
                    'presentes' => $conteo['presente'] ?? 0,
                    'faltas' => $conteo['falta'] ?? 0,
                    'justificadas' => $conteo['justificado'] ?? 0,
                    'profesor' => $estadoProfesor,
                ]);
            }
        }

        // Más recientes primero; en un mismo día, por hora
        return $clases->sort(function ($a, $b) {
            return [$b['fecha'], $a['horario']->hora_inicio] <=> [$a['fecha'], $b['horario']->hora_inicio];
        })->values();
    }
}
