<?php

namespace App\Support;

use App\Models\Asistencia;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Los reportes de las computadoras de los laboratorios, y el Uso Libre que se
 * queda abierto cuando una se apaga.
 *
 * El script de cada equipo (Windows y Mac) pregunta al sistema cada 5 a 15
 * segundos si su máquina está ocupada. Aquí se apunta la hora de cada consulta,
 * así que el sistema sabe cuándo se reportó cada computadora por última vez.
 *
 * Eso cierra un hueco: apagar la computadora no le avisa nada al sistema, y el
 * Uso Libre del alumno que se iba sin terminarlo se quedaba abierto a su nombre.
 * La máquina seguía "ocupada", nadie más podía registrarse en ella, quien la
 * volvía a encender la encontraba desbloqueada y el alumno no podía abrir otro
 * Uso Libre ese día. Ahora se cierra solo:
 *
 *   - Al iniciar sesión otra vez en esa computadora: el script lo avisa con
 *     reiniciar=1, y la sesión anterior terminó seguro.
 *   - Si deja de reportarse MINUTOS_SIN_REPORTE minutos: se apagó. Lo revisa
 *     cada minuto el comando equipos:cerrar-uso-libre-apagados.
 *
 * La hora de salida es la del último reporte, que es cuando se apagó.
 *
 * Sólo se cierra el Uso Libre de una máquina que se reportó DESPUÉS de que el
 * alumno se registrara. Así no se toca nada en las computadoras sin el script, ni
 * el registro que un alumno hace desde su celular antes de encender la máquina.
 *
 * Si lo que falló fue la red y no la computadora, al volver la red la máquina se
 * reporta sin haber reiniciado. Entonces se reabre el Uso Libre que se cerró por
 * silencio y el alumno sigue trabajando sin enterarse.
 */
class ReportesDeEquipos
{
    /** Minutos sin reportarse a partir de los cuales una máquina se da por apagada. */
    const MINUTOS_SIN_REPORTE = 10;

    /**
     * Apunta que la máquina acaba de reportarse. Devuelve su reporte anterior, o
     * null si no había ninguno.
     */
    public static function registrar(int $centro, int $maquina): ?Carbon
    {
        $anterior = self::ultimo($centro, $maquina);

        Cache::put(self::clave($centro, $maquina), now()->toDateTimeString(), now()->addDays(2));

        return $anterior;
    }

    /** Última vez que la máquina se reportó, o null si no se sabe. */
    public static function ultimo(int $centro, int $maquina): ?Carbon
    {
        $valor = Cache::get(self::clave($centro, $maquina));

        return $valor ? Carbon::parse($valor) : null;
    }

    /**
     * La computadora inició una sesión nueva: se cierra el Uso Libre que tenía
     * abierto de la sesión anterior, con la hora de su último reporte.
     */
    public static function cerrarAlReiniciar(int $centro, int $maquina, ?Carbon $reporteAnterior): void
    {
        // Lo que se hubiera cerrado por silencio ya no debe reabrirse: la
        // computadora sí se apagó.
        Cache::forget(self::claveCierre($centro, $maquina));

        if (! $reporteAnterior) {
            return;
        }

        $abiertas = self::usoLibreAbierto()
            ->where('centro_computo_id', $centro)
            ->where('numero_maquina', $maquina)
            ->get();

        foreach ($abiertas as $asistencia) {
            if (self::seReportoDespuesDelRegistro($asistencia, $reporteAnterior)) {
                self::cerrar($asistencia, $reporteAnterior, 'la computadora inició una sesión nueva');
            }
        }
    }

    /**
     * Cierra el Uso Libre de las máquinas que llevan MINUTOS_SIN_REPORTE minutos
     * sin reportarse. Devuelve cuántos cerró.
     */
    public static function cerrarDeEquiposApagados(): int
    {
        $limite = now()->subMinutes(self::MINUTOS_SIN_REPORTE);
        $cerrados = 0;

        foreach (self::usoLibreAbierto()->get() as $asistencia) {
            $ultimo = self::ultimo((int) $asistencia->centro_computo_id, (int) $asistencia->numero_maquina);

            // Sin datos (máquina sin el script) o se reportó hace poco: sigue encendida.
            if (! $ultimo || $ultimo->gt($limite)) {
                continue;
            }

            // No se ha encendido desde que el alumno se registró.
            if (! self::seReportoDespuesDelRegistro($asistencia, $ultimo)) {
                continue;
            }

            self::cerrar($asistencia, $ultimo, 'la computadora dejó de reportarse');

            // Por si fue la red: se recuerda para reabrirlo cuando vuelva.
            Cache::put(self::claveCierre((int) $asistencia->centro_computo_id, (int) $asistencia->numero_maquina), [
                'id'     => $asistencia->id,
                'salida' => $asistencia->fecha_hora_salida,
            ], now()->endOfDay());

            $cerrados++;
        }

        return $cerrados;
    }

    /**
     * La máquina volvió a reportarse sin haber reiniciado: si su Uso Libre se
     * cerró por silencio, fue un corte de red y se reabre.
     */
    public static function reabrirSiFueCorteDeRed(int $centro, int $maquina): void
    {
        $cierre = Cache::pull(self::claveCierre($centro, $maquina));
        if (! is_array($cierre)) {
            return;
        }

        $asistencia = Asistencia::find($cierre['id'] ?? null);

        // Sólo si nadie lo tocó después: sigue siendo de hoy, con la misma hora
        // de salida que le puso el cierre automático...
        if (! $asistencia
            || $asistencia->tipo !== 'Uso Libre'
            || $asistencia->fecha_hora_salida !== ($cierre['salida'] ?? null)
            || ! Carbon::parse($asistencia->fecha)->isToday()) {
            return;
        }

        // ...y ni la máquina ni el alumno tienen ya otra sesión abierta.
        $otraDelAlumno = self::usoLibreAbierto()->where('user_id', $asistencia->user_id)->exists();
        if ($otraDelAlumno || Asistencia::estaOcupada($centro, $maquina)) {
            return;
        }

        $asistencia->fecha_hora_salida = null;
        $asistencia->save();

        Log::info("Uso Libre #{$asistencia->id} reabierto: la PC #{$maquina} (centro {$centro}) volvió a reportarse sin reiniciar; fue un corte de red.");
    }

    /** Sesiones de Uso Libre de hoy, en una máquina, que siguen abiertas. */
    protected static function usoLibreAbierto()
    {
        return Asistencia::where('tipo', 'Uso Libre')
            ->whereDate('fecha', Carbon::today())
            ->whereNull('fecha_hora_salida')
            ->whereNotNull('numero_maquina')
            ->whereNotNull('centro_computo_id');
    }

    protected static function seReportoDespuesDelRegistro(Asistencia $asistencia, Carbon $reporte): bool
    {
        return $asistencia->fecha_hora_registro
            && $reporte->gt(Carbon::parse($asistencia->fecha_hora_registro));
    }

    protected static function cerrar(Asistencia $asistencia, Carbon $salida, string $motivo): void
    {
        $asistencia->fecha_hora_salida = $salida->toDateTimeString();
        $asistencia->save();

        Log::info("Uso Libre #{$asistencia->id} cerrado solo en la PC #{$asistencia->numero_maquina} (centro {$asistencia->centro_computo_id}): {$motivo}. Salida: {$asistencia->fecha_hora_salida}.");
    }

    protected static function clave(int $centro, int $maquina): string
    {
        return "equipo-reporte:{$centro}:{$maquina}";
    }

    protected static function claveCierre(int $centro, int $maquina): string
    {
        return "equipo-cierre-automatico:{$centro}:{$maquina}";
    }
}
