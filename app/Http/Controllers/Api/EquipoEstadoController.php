<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Asistencia;
use App\Support\DesbloqueoDePersonal;
use App\Support\ReportesDeEquipos;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Consulta que hacen las computadoras de los laboratorios.
 *
 * El script que corre en cada equipo pregunta cada pocos segundos si su máquina
 * tiene una sesión activa. Mientras la respuesta sea que no, el equipo se
 * mantiene bloqueado con el sistema en pantalla completa; en cuanto el alumno
 * registra su asistencia o su entrada de uso libre, se libera.
 *
 * A propósito NO devuelve ningún dato personal: sólo si la máquina está ocupada
 * y de qué tipo es la sesión. Así, aunque alguien consulte la dirección desde la
 * red del instituto, no obtiene información de nadie.
 */
class EquipoEstadoController extends Controller
{
    /** Zona horaria del plantel, la misma que usa el resto del sistema. */
    const ZONA = 'America/Hermosillo';

    /**
     * Minutos de margen al terminar una clase.
     *
     * Sin esto, el equipo volvería a bloquearse en el minuto exacto en que acaba
     * la clase, con el alumno todavía cerrando lo que estaba haciendo.
     */
    const MARGEN_FIN_CLASE = 15;

    public function estado(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'centro'  => 'required|integer|min:1',
            'maquina' => 'required|integer|min:1',
        ]);

        $centro  = (int) $datos['centro'];
        $maquina = (int) $datos['maquina'];
        $hoy     = Carbon::today(self::ZONA)->toDateString();

        // Se apunta que la máquina está encendida (ver ReportesDeEquipos).
        $reporteAnterior = ReportesDeEquipos::registrar($centro, $maquina);

        // El script avisa con reiniciar=1 cuando arranca una sesión nueva de
        // Windows o Mac: si el personal dejó el equipo liberado y no cerró sesión,
        // el permiso no se hereda al siguiente que encienda la computadora. Lo
        // mismo con el Uso Libre que el alumno anterior dejó abierto al apagarla.
        if ($request->boolean('reiniciar')) {
            DesbloqueoDePersonal::cerrar($centro, $maquina);
            ReportesDeEquipos::cerrarAlReiniciar($centro, $maquina, $reporteAnterior);
        } else {
            // Si su Uso Libre se cerró porque dejó de reportarse, pero la
            // computadora nunca se apagó, fue la red: se reabre.
            ReportesDeEquipos::reabrirSiFueCorteDeRed($centro, $maquina);
        }

        // --- Liberada por el personal: mientras dure su sesión, no se bloquea ---
        if (DesbloqueoDePersonal::activo($centro, $maquina)) {
            return response()->json([
                'ocupada' => true,
                'tipo'    => 'Personal',
            ]);
        }

        // --- Uso libre: la sesión sigue abierta mientras no tenga hora de salida ---
        $usoLibre = Asistencia::where('centro_computo_id', $centro)
            ->where('numero_maquina', $maquina)
            ->where('tipo', 'Uso Libre')
            ->whereDate('fecha', $hoy)
            ->whereNull('fecha_hora_salida')
            ->exists();

        if ($usoLibre) {
            return response()->json([
                'ocupada' => true,
                'tipo'    => 'Uso Libre',
            ]);
        }

        // --- Clase: cuenta sólo mientras la clase está en curso ---
        //
        // Los registros de clase no llevan hora de salida (al pasar lista no se
        // marca), así que no sirve el mismo criterio del uso libre: la máquina
        // quedaría liberada el resto del día tras la primera clase. Aquí se
        // comprueba que la hora actual caiga dentro del horario de esa clase.
        $ahora = Carbon::now(self::ZONA);

        $enClase = Asistencia::with('horario')
            ->where('centro_computo_id', $centro)
            ->where('numero_maquina', $maquina)
            ->where('tipo', 'Clase')
            ->whereDate('fecha', $hoy)
            ->whereNotIn('estado', ['falta', 'justificado'])
            ->get()
            ->contains(function ($asistencia) use ($ahora) {
                if (!$asistencia->horario) {
                    return false;
                }

                $inicio = Carbon::parse($asistencia->horario->hora_inicio, self::ZONA)
                    ->setDateFrom($ahora);
                $terminaClase = Carbon::parse($asistencia->horario->hora_fin, self::ZONA)
                    ->setDateFrom($ahora);
                $fin = $terminaClase->copy()->addMinutes(self::MARGEN_FIN_CLASE);

                // El encargado la cerró antes de que acabara la clase ("Forzar
                // salida" o "Liberar clase" en el Monitor): se bloquea. El cierre
                // automático del Monitor pone justo la hora de fin, y ése no
                // quita el margen.
                if ($asistencia->fecha_hora_salida
                    && Carbon::parse($asistencia->fecha_hora_salida, self::ZONA)->lt($terminaClase)) {
                    return false;
                }

                return $ahora->betweenIncluded($inicio, $fin);
            });

        return response()->json([
            'ocupada' => $enClase,
            'tipo'    => $enClase ? 'Clase' : null,
        ]);
    }
}
