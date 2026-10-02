<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Horario;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Las clases a las que un alumno faltó sin que le quedara ningún registro.
 *
 * La falta de un alumno sólo existe en el sistema si alguien pasa lista y lo
 * marca. Cuando los alumnos se registran solos y nadie guarda la lista, el que no
 * vino simplemente no tiene registro: su Inicio ya lo mostraba como "Falta", pero
 * "Mi Progreso" contaba sólo los registros, así que quien se registró 1 de 10
 * veces veía 100%.
 *
 * Aquí una clase cuenta como falta sin registro sólo si se sabe que se dio y que
 * se tomó asistencia en el sistema:
 *
 *   - ese día OTROS alumnos del grupo sí quedaron registrados, y
 *   - el profesor no está marcado como falta o justificado (entonces no hubo clase).
 *
 * Si ese día nadie quedó registrado (las semanas de hojas de papel, o una lista
 * que nunca se pasó) no se sabe si el alumno fue, y no se cuenta. Tampoco las
 * clases de antes de que lo inscribieran al grupo, cuando se conoce esa fecha, ni
 * la de hoy mientras no termine.
 */
class FaltasSinRegistro
{
    /**
     * @return Collection<int, array{horario: Horario, fecha: string}>
     */
    public static function de(User $alumno, ?int $semestreId = null): Collection
    {
        $grupos = $alumno->grupos()->get();
        if ($grupos->isEmpty()) {
            return collect();
        }

        $horarios = Horario::with('materia')
            ->whereIn('grupo_id', $grupos->pluck('id'))
            ->when($semestreId, fn($q) => $q->where('semestre_id', $semestreId))
            ->get()
            ->keyBy('id');

        if ($horarios->isEmpty()) {
            return collect();
        }

        $ids = $horarios->keys();
        $hoy = Carbon::today()->toDateString();
        $horaActual = Carbon::now()->format('H:i:s');

        // Días en que, en cada clase, alguien quedó registrado
        $diasConRegistro = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->whereDate('fecha', '<=', $hoy)
            ->select('horario_id', DB::raw('DATE(fecha) as dia'))
            ->groupBy('horario_id', DB::raw('DATE(fecha)'))
            ->get();

        $propios = self::llaves(Asistencia::where('user_id', $alumno->id)
            ->where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->get(['horario_id', 'fecha']));

        $noImpartidas = self::llaves(AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereIn('estado', ['falta', 'justificado'])
            ->get(['horario_id', 'fecha']));

        // Desde cuándo está en cada grupo, si se sabe
        $inscritoDesde = $grupos->mapWithKeys(fn($g) => [
            $g->id => $g->pivot->created_at ? Carbon::parse($g->pivot->created_at)->toDateString() : null,
        ]);

        $faltas = collect();

        foreach ($diasConRegistro as $dia) {
            $llave = $dia->horario_id . '|' . $dia->dia;
            $horario = $horarios->get($dia->horario_id);

            if (isset($propios[$llave]) || isset($noImpartidas[$llave]) || ! $horario) {
                continue;
            }

            // La de hoy, sólo cuando ya terminó: todavía puede registrarse
            if ($dia->dia === $hoy && $horario->hora_fin > $horaActual) {
                continue;
            }

            $desde = $inscritoDesde[$horario->grupo_id] ?? null;
            if ($desde && $dia->dia < $desde) {
                continue;
            }

            $faltas->push(['horario' => $horario, 'fecha' => $dia->dia]);
        }

        return $faltas;
    }

    /** "horario_id|AAAA-MM-DD" => true, para buscar rápido. */
    protected static function llaves($registros): array
    {
        return $registros
            ->mapWithKeys(fn($r) => [$r->horario_id . '|' . substr((string) $r->fecha, 0, 10) => true])
            ->all();
    }
}
