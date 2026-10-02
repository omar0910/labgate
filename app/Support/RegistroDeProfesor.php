<?php

namespace App\Support;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Horario;
use App\Services\AvisosCorreo;
use Carbon\Carbon;

/**
 * Registrar si el profesor dio su clase (asistió, falta o justificado), desde
 * "Gestión de Clases" del encargado o "Clases de hoy" del administrador.
 *
 * Las dos pantallas tenían el mismo código copiado, con los mismos huecos: se
 * podía registrar una clase en un día que no le toca (la de un martes con fecha
 * de miércoles), marcar "asistió" en una fecha que todavía no llega, y cada vez
 * que se volvía a guardar una falta (p. ej. sólo para cambiar la nota) se
 * borraban otra vez las asistencias de los alumnos de ese día.
 */
class RegistroDeProfesor
{
    /** Qué impide registrar ese estado en esa clase y fecha, o null si se puede. */
    public static function problema(Horario $horario, string $fecha, string $estado): ?string
    {
        // Por adelantado sólo tiene sentido registrar que el profesor no la dará
        // (avisó que va a faltar); "asistió" se sabe hasta ese día.
        return $horario->problemaParaLaFecha(
            $fecha,
            in_array($estado, ['falta', 'justificado'], true)
                ? null
                : 'Esa clase todavía no llega: por adelantado sólo se puede registrar una falta o una justificación.'
        );
    }

    /**
     * Guarda el estado y la nota. Si el estado cambió, avisa al profesor y, si la
     * clase no se dio, quita las asistencias de los alumnos de ese día (una por
     * una, para que cada máquina recupere el uso que se le había sumado).
     *
     * Devuelve cuántas asistencias de alumnos se quitaron.
     */
    public static function registrar(Horario $horario, string $fecha, string $estado, ?string $observaciones): int
    {
        $registro = AsistenciaProfesor::updateOrCreate(
            ['horario_id' => $horario->id, 'fecha' => $fecha],
            ['estado' => $estado, 'observaciones' => $observaciones]
        );

        // Mismo estado que ya tenía (p. ej. sólo se cambió la nota): nada más que hacer
        if (! $registro->wasRecentlyCreated && ! $registro->wasChanged('estado')) {
            return 0;
        }

        if ($horario->user) {
            AvisosCorreo::asistenciaDeProfesor($horario->user, $registro);
        }

        if (! in_array($estado, ['falta', 'justificado'], true)) {
            return 0;
        }

        // Se borran las del DÍA DE LA CLASE ('fecha'), no las que se grabaron ese
        // día ('fecha_hora_registro'), que serían otras.
        return Asistencia::borrarUnaPorUna(
            Asistencia::where('horario_id', $horario->id)
                ->where('tipo', 'Clase')
                ->whereDate('fecha', $fecha)
        );
    }

    /**
     * Lo registrado del profesor y cuántos alumnos quedaron registrados, por
     * clase, en una fecha (antes la vista lo consultaba clase por clase).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection}
     */
    public static function delDia($horarios, string $fecha): array
    {
        $ids = $horarios->pluck('id');

        $reportes = AsistenciaProfesor::whereIn('horario_id', $ids)
            ->whereDate('fecha', $fecha)
            ->get()
            ->keyBy('horario_id');

        $registrados = Asistencia::where('tipo', 'Clase')
            ->whereIn('horario_id', $ids)
            ->whereDate('fecha', $fecha)
            ->selectRaw('horario_id, COUNT(*) as total')
            ->groupBy('horario_id')
            ->pluck('total', 'horario_id');

        return [$reportes, $registrados];
    }

    /** Sólo fechas reales AAAA-MM-DD; cualquier otra cosa, null. */
    public static function fechaValida($valor): ?string
    {
        return is_string($valor)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes)
            && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1])
            ? $valor
            : null;
    }
}
