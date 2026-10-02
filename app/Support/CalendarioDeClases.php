<?php

namespace App\Support;

use App\Models\Horario;
use Carbon\Carbon;

/**
 * Los días en que le tocaba darse a cada clase.
 *
 * Lo usan los reportes (cuántas clases debía dar cada profesor) y la pantalla de
 * clases pendientes (cuáles no tienen asistencia capturada), para que las dos
 * cuenten exactamente igual.
 *
 * La regla importante: una clase cuenta DESDE QUE SE REGISTRÓ en el sistema, no
 * desde el inicio del semestre. Si los horarios se capturan (o se importan) un
 * mes tarde, las semanas anteriores no deben aparecer como clases no dadas: el
 * sistema simplemente no sabía de ellas. La excepción son las fechas anteriores
 * a las que alguien ya les capturó asistencia (de las hojas de papel, por
 * ejemplo): esas sí cuentan, con lo que se haya registrado.
 */
class CalendarioDeClases
{
    const DIAS = [
        'Lunes' => 1, 'Martes' => 2, 'Miércoles' => 3, 'Jueves' => 4,
        'Viernes' => 5, 'Sábado' => 6, 'Domingo' => 7,
    ];

    /**
     * Días del rango en que tocaba esta clase.
     *
     * Devuelve ['clases' => [...fechas Y-m-d], 'inhabiles' => [...fechas]]: los
     * días inhábiles en que le tocaba se entregan aparte, porque no cuentan como
     * clase pero el reporte los usa en su cálculo de respaldo.
     */
    public static function fechas(Horario $horario, $desde, $hasta, array $diasInhabiles = []): array
    {
        $desde = Carbon::parse($desde)->startOfDay();
        $hasta = Carbon::parse($hasta)->startOfDay();
        $inhabiles = array_flip(array_map([self::class, 'soloFecha'], $diasInhabiles));

        $clases = [];
        $festivas = [];

        $anotar = function (Carbon $fecha) use (&$clases, &$festivas, $inhabiles) {
            $texto = $fecha->format('Y-m-d');
            if (isset($inhabiles[$texto])) {
                $festivas[] = $texto;
            } else {
                $clases[] = $texto;
            }
        };

        // Reserva de un día concreto
        if ($horario->fecha_especial) {
            $fecha = Carbon::parse($horario->fecha_especial)->startOfDay();
            if ($fecha->betweenIncluded($desde, $hasta)) {
                $anotar($fecha);
            }

            return ['clases' => $clases, 'inhabiles' => $festivas];
        }

        // Clase fija de cada semana
        $dia = self::DIAS[$horario->dia_semana] ?? null;
        if (! $dia) {
            return ['clases' => [], 'inhabiles' => []];
        }

        // Se salta directo al primer día de la semana que le toca y de ahí de 7 en 7.
        $fecha = $desde->copy();
        while ($fecha->dayOfWeekIso !== $dia) {
            $fecha->addDay();
        }

        for (; $fecha->lte($hasta); $fecha->addWeek()) {
            $anotar($fecha->copy());
        }

        return ['clases' => $clases, 'inhabiles' => $festivas];
    }

    /** Desde cuándo cuenta la clase: el día que se registró, o el inicio del semestre si fue antes. */
    public static function vigenteDesde(Horario $horario, $semestre): Carbon
    {
        $inicio = Carbon::parse($semestre->fecha_inicio)->startOfDay();

        if (! $horario->created_at) {
            return $inicio;
        }

        $registrada = Carbon::parse($horario->created_at)->startOfDay();

        return $registrada->greaterThan($inicio) ? $registrada : $inicio;
    }

    /**
     * Cuántas clases debía dar este horario en el semestre y cuántas hasta hoy.
     *
     * $fechasConRegistro son los días en que ya hay asistencia capturada (del
     * profesor o de alumnos): esos cuentan aunque sean anteriores al registro
     * del horario.
     */
    public static function esperadas(Horario $horario, $semestre, array $diasInhabiles, $fechasConRegistro, $desde = null, $hasta = null): array
    {
        // Por omisión, todo el semestre; los reportes con filtro de fechas mandan su rango.
        $calendario = self::fechas(
            $horario,
            $desde ?? $semestre->fecha_inicio,
            $hasta ?? $semestre->fecha_fin,
            $diasInhabiles
        );

        $desde = self::vigenteDesde($horario, $semestre)->format('Y-m-d');
        $hoy = now()->format('Y-m-d');
        $horaActual = now()->format('H:i');
        $horaFin = $horario->hora_fin ? substr((string) $horario->hora_fin, 0, 5) : '23:59';
        $capturadas = array_flip(array_map([self::class, 'soloFecha'], collect($fechasConRegistro)->all()));

        $total = 0;
        $hastaHoy = 0;
        $fechasHastaHoy = [];

        foreach ($calendario['clases'] as $fecha) {
            if ($fecha < $desde && ! isset($capturadas[$fecha])) {
                continue;   // antes de que la clase existiera en el sistema, y sin nada capturado
            }

            $total++;

            // La clase de HOY cuenta cuando ya terminó o cuando ya tiene registro.
            // Antes de eso no se le pueden pedir cuentas al profesor: aparecía con
            // 0% de cumplimiento desde la mañana, con la clase aún por darse.
            $yaDebioDarse = $fecha < $hoy
                || ($fecha === $hoy && (isset($capturadas[$fecha]) || $horaActual >= $horaFin));

            if ($yaDebioDarse) {
                $hastaHoy++;
                $fechasHastaHoy[] = $fecha;
            }
        }

        // 'fechas_hoy': cuáles son las que ya debieron darse, para saber cuáles
        // siguen sin registro.
        return ['total' => $total, 'hoy' => $hastaHoy, 'fechas_hoy' => $fechasHastaHoy, 'festivas' => $calendario['inhabiles']];
    }

    /** "2026-08-25 00:00:00", un Carbon o "2026-08-25" -> "2026-08-25". */
    public static function soloFecha($valor): string
    {
        return substr((string) ($valor instanceof \DateTimeInterface ? $valor->format('Y-m-d') : $valor), 0, 10);
    }
}
