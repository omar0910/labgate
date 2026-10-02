<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\DiaInhabil;
use App\Models\Horario;
use App\Models\Semestre;
use Carbon\Carbon;

/**
 * Las clases de una semana y en qué quedó cada una.
 *
 * La usan el panel de inicio (el progreso de la semana) y la vista "Semana en
 * curso" (el detalle), para que las dos cuenten exactamente igual. Sigue las
 * mismas reglas que los reportes (CalendarioDeClases):
 *
 *  - Los días inhábiles no cuentan.
 *  - Una clase cuenta desde que su horario se registró en el sistema; las de
 *    antes sólo si alguien ya les capturó asistencia.
 *  - Lo que decide el estado es el registro del profesor. Sin registro, la clase
 *    está "por registrar" si ya terminó, "en curso" si se está dando, o "próxima".
 */
class AgendaSemanal
{
    const ESTADOS = [
        'asistio'       => 'Impartida',
        'justificado'   => 'Justificada',
        'falta'         => 'Falta',
        'por_registrar' => 'Por registrar',
        'en_curso'      => 'En curso',
        'proxima'       => 'Próxima',
    ];

    /**
     * @param  Carbon|string  $diaDeLaSemana  cualquier día de la semana que se quiere ver
     */
    public static function de(?Semestre $semestre, $diaDeLaSemana, $centroId = null): array
    {
        $lunes = Carbon::parse($diaDeLaSemana)->startOfWeek();
        $domingo = $lunes->copy()->endOfWeek();

        $agenda = [
            'lunes'        => $lunes,
            'domingo'      => $domingo,
            'clases'       => collect(),
            'conteo'       => array_fill_keys(array_keys(self::ESTADOS), 0),
            'total'        => 0,
            'yaDebieron'   => 0,
            'cumplimiento' => null,
            'inhabiles'    => [],
            'anteriores'   => 0,
            'enPeriodo'    => false,
        ];

        if (! $semestre) {
            return $agenda;
        }

        // Sólo la parte de la semana que cae dentro del semestre
        $desde = max($lunes->format('Y-m-d'), CalendarioDeClases::soloFecha($semestre->fecha_inicio));
        $hasta = min($domingo->format('Y-m-d'), CalendarioDeClases::soloFecha($semestre->fecha_fin));

        if ($desde > $hasta) {
            return $agenda;
        }

        $agenda['enPeriodo'] = true;

        $horarios = Horario::with(['materia', 'grupo', 'user', 'centroComputo'])
            ->where('semestre_id', $semestre->id)
            ->when($centroId && $centroId !== 'todos', fn($q) => $q->where('centro_computo_id', $centroId))
            ->get();

        $ids = $horarios->pluck('id');

        $agenda['inhabiles'] = DiaInhabil::where('semestre_id', $semestre->id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->get()
            ->mapWithKeys(fn($d) => [CalendarioDeClases::soloFecha($d->fecha) => $d->motivo])
            ->all();

        $registros = AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->get()
            ->keyBy(fn($r) => $r->horario_id . '|' . CalendarioDeClases::soloFecha($r->fecha));

        // Alumnos que sí vinieron, para ver de un vistazo si la clase se dio
        $alumnos = Asistencia::where('tipo', 'Clase')
            ->presenciales()
            ->whereIn('horario_id', $ids)
            ->whereBetween('fecha', [$desde, $hasta])
            ->selectRaw('horario_id, fecha, COUNT(*) as total')
            ->groupBy('horario_id', 'fecha')
            ->get()
            ->mapWithKeys(fn($r) => [$r->horario_id . '|' . CalendarioDeClases::soloFecha($r->fecha) => (int) $r->total])
            ->all();

        $hoy = now()->format('Y-m-d');
        $horaActual = now()->format('H:i');
        $clases = [];

        foreach ($horarios as $horario) {
            $vigenteDesde = CalendarioDeClases::vigenteDesde($horario, $semestre)->format('Y-m-d');
            $inicio = $horario->hora_inicio ? substr((string) $horario->hora_inicio, 0, 5) : '00:00';
            $fin = $horario->hora_fin ? substr((string) $horario->hora_fin, 0, 5) : '23:59';

            foreach (CalendarioDeClases::fechas($horario, $desde, $hasta, array_keys($agenda['inhabiles']))['clases'] as $fecha) {
                $llave = $horario->id . '|' . $fecha;
                $registro = $registros[$llave] ?? null;

                // Antes de que el horario existiera en el sistema, y sin nada capturado
                if ($fecha < $vigenteDesde && ! $registro) {
                    $agenda['anteriores']++;
                    continue;
                }

                if ($registro) {
                    $estado = in_array($registro->estado, ['falta', 'justificado']) ? $registro->estado : 'asistio';
                } elseif ($fecha < $hoy || ($fecha === $hoy && $horaActual >= $fin)) {
                    $estado = 'por_registrar';
                } elseif ($fecha === $hoy && $horaActual >= $inicio) {
                    $estado = 'en_curso';
                } else {
                    $estado = 'proxima';
                }

                $agenda['conteo'][$estado]++;

                $clases[] = [
                    'horario'       => $horario,
                    'fecha'         => $fecha,
                    'estado'        => $estado,
                    'alumnos'       => $alumnos[$llave] ?? 0,
                    'observaciones' => $registro->observaciones ?? null,
                ];
            }
        }

        $agenda['clases'] = collect($clases)
            ->sortBy(fn($c) => $c['fecha'] . ' ' . $c['horario']->hora_inicio)
            ->values();

        $c = $agenda['conteo'];
        $agenda['total'] = count($clases);
        $agenda['yaDebieron'] = $c['asistio'] + $c['justificado'] + $c['falta'] + $c['por_registrar'];

        // Igual que en los reportes: impartidas + justificadas sobre las que ya debieron darse
        $agenda['cumplimiento'] = $agenda['yaDebieron'] > 0
            ? min(100, (int) round((($c['asistio'] + $c['justificado']) / $agenda['yaDebieron']) * 100))
            : null;

        return $agenda;
    }
}
