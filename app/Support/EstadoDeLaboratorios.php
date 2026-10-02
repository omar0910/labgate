<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\CentroComputo;
use App\Models\Equipo;
use App\Models\Semestre;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Cómo están los laboratorios en este momento: PCs en uso, PCs en mantenimiento
 * y la clase que se está dando.
 *
 * Lo ven el inicio del encargado, el inicio del administrador y el Monitor de
 * Laboratorios, siempre con el mismo cálculo (antes vivía sólo en el inicio del
 * encargado, y el Monitor sólo mostraba la capacidad de cada laboratorio).
 */
class EstadoDeLaboratorios
{
    /**
     * Una fila por laboratorio: (object) [centro, ocupadas, mantenimiento, clases].
     *
     * @param  Collection|null  $clasesAhora  las clases en curso, si ya se calcularon
     *                                        (ver clasesEnCurso()); si no, se calculan.
     */
    public static function ahora(?Collection $clasesAhora = null): Collection
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $ahora = Carbon::now()->format('H:i:s');

        if ($clasesAhora === null) {
            $clasesAhora = self::clasesEnCurso(AgendaSemanal::de(Semestre::activo(), Carbon::now()));
        }

        // PCs ocupadas: uso libre sin salida, o clase que no ha terminado (con PC)
        $ocupadasPorLab = Asistencia::whereDate('fecha', $hoy)
            ->whereNotNull('numero_maquina')
            ->whereNull('fecha_hora_salida')
            ->where(function ($q) use ($ahora) {
                $q->where('tipo', '!=', 'Clase')
                    ->orWhereHas('horario', fn($h) => $h->whereTime('hora_fin', '>', $ahora));
            })
            ->get(['centro_computo_id', 'numero_maquina'])
            ->groupBy('centro_computo_id')
            ->map(fn($registros) => $registros->unique('numero_maquina')->count());

        $mantenimientoPorLab = Equipo::where('estado', '!=', 'disponible')
            ->selectRaw('centro_computo_id, COUNT(*) as total')
            ->groupBy('centro_computo_id')
            ->pluck('total', 'centro_computo_id');

        return CentroComputo::orderBy('nombre_centro')->get()->map(fn($centro) => (object) [
            'centro'        => $centro,
            'ocupadas'      => (int) ($ocupadasPorLab[$centro->id] ?? 0),
            'mantenimiento' => (int) ($mantenimientoPorLab[$centro->id] ?? 0),
            'clases'        => $clasesAhora->filter(fn($c) => (int) $c['horario']->centro_computo_id === (int) $centro->id)->values(),
        ]);
    }

    /**
     * Las clases de hoy que están en curso por la hora, sin importar si ya se
     * registró al profesor. Recibe la agenda de la semana (AgendaSemanal::de).
     */
    public static function clasesEnCurso(array $semana): Collection
    {
        $hoy = Carbon::today()->format('Y-m-d');
        $ahora = Carbon::now()->format('H:i:s');

        return $semana['clases']
            ->where('fecha', $hoy)
            ->filter(fn($c) => $c['horario']->hora_inicio <= $ahora && $c['horario']->hora_fin > $ahora)
            ->values();
    }
}
