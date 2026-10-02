<?php

namespace App\Services;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\User;
use App\Mail\AvisoAsistenciaClase;
use App\Mail\AvisoAsistenciaProfesor;
use App\Mail\AvisoUsoLibreEntrada;
use App\Mail\AvisoUsoLibreSalida;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo que se mandan en el momento, a diferencia del resumen semanal.
 *
 * Todo lo de aquí cumple tres reglas:
 *
 *   1. NUNCA interrumpe lo que el usuario estaba haciendo. Si el correo falla, se
 *      anota en el registro y la página sigue su curso: que no salga un correo no
 *      puede impedir que un alumno registre su asistencia.
 *
 *   2. Se encola en lugar de enviarse en el momento. El alumno no debe esperar a
 *      que Office 365 conteste para ver su confirmación en pantalla.
 *
 *   3. Se reparte en el tiempo cuando son muchos. Al guardar una lista de cuarenta
 *      alumnos saldrían cuarenta correos de golpe, y los servidores de correo
 *      institucionales suelen frenar los envíos masivos repentinos.
 */
class AvisosCorreo
{
    /**
     * Cuántos correos se dejan salir por minuto.
     *
     * Office 365 corta los envíos que pasan de unos 30 por minuto desde la misma
     * cuenta, así que se deja margen: si dos o tres profesores pasan lista a la
     * vez, el total sigue por debajo del límite.
     */
    const POR_MINUTO = 10;

    /** Clave donde se lleva la cuenta de los avisos de cada minuto. */
    const CLAVE_RITMO = 'labgate:avisos-correo:';

    /**
     * Confirma al alumno el estado de su asistencia en una clase.
     *
     * $motivo distingue quién lo provocó, porque el texto cambia:
     *   'alumno'   - lo registró él mismo desde su panel
     *   'profesor' - el profesor pasó lista
     *   'ajuste'   - lo cambió un administrador o encargado
     */
    public static function asistenciaDeClase(User $alumno, Asistencia $asistencia, string $motivo = 'alumno', int $posicion = 0): void
    {
        self::enviar(
            $alumno,
            new AvisoAsistenciaClase($alumno, $asistencia, $motivo),
            $posicion,
            'asistencia de clase'
        );
    }

    /** Avisa al alumno de que su sesión de uso libre quedó abierta. */
    public static function entradaUsoLibre(User $alumno, Asistencia $sesion): void
    {
        self::enviar($alumno, new AvisoUsoLibreEntrada($alumno, $sesion), 0, 'entrada de uso libre');
    }

    /** Avisa al alumno de que su sesión de uso libre terminó, con el tiempo que estuvo. */
    public static function salidaUsoLibre(User $alumno, Asistencia $sesion): void
    {
        self::enviar($alumno, new AvisoUsoLibreSalida($alumno, $sesion), 0, 'salida de uso libre');
    }

    /** Avisa al profesor del estado que le registraron en una de sus clases. */
    public static function asistenciaDeProfesor(User $profesor, AsistenciaProfesor $registro): void
    {
        self::enviar($profesor, new AvisoAsistenciaProfesor($profesor, $registro), 0, 'asistencia de profesor');
    }

    // ------------------------------------------------------------------

    /**
     * Encola un correo sin dejar que un fallo se propague a la petición web.
     *
     * $posicion sirve para repartir los envíos masivos: con doce por minuto, el
     * alumno número trece de la lista sale un minuto más tarde.
     */
    protected static function enviar(User $destinatario, $correo, int $posicion, string $descripcion): void
    {
        try {
            if (empty($destinatario->email)) {
                return;
            }

            $envio  = Mail::to($destinatario->email);
            $minutos = self::minutosDeEspera($posicion);

            if ($minutos > 0) {
                $envio->later(now()->addMinutes($minutos), $correo);
                return;
            }

            $envio->queue($correo);
        } catch (\Throwable $e) {
            // A propósito se traga el error: el aviso es secundario y la acción
            // del usuario ya se completó.
            Log::warning(sprintf(
                'No se pudo encolar el aviso de %s para %s: %s',
                $descripcion,
                $destinatario->email ?? 'sin correo',
                $e->getMessage()
            ));
        }
    }

    /**
     * Cuántos minutos hay que retrasar este aviso para no saturar el correo.
     *
     * La cuenta se lleva en la caché y es de TODO el sistema, no de esta petición:
     * si dos profesores pasan lista al mismo tiempo, sus correos se reparten entre
     * ambos en lugar de salir los veinte de cada uno a la vez.
     *
     * $posicionLocal es el respaldo por si la caché no está disponible: al menos
     * reparte los de esta petición.
     */
    protected static function minutosDeEspera(int $posicionLocal): int
    {
        try {
            $clave = self::CLAVE_RITMO . now()->format('YmdHi');

            // Los avisos que ya se apuntaron para este minuto.
            $emitidos = (int) Cache::get($clave, 0);

            // La clave vive unos minutos: pasado el minuto ya no interesa.
            Cache::put($clave, $emitidos + 1, now()->addMinutes(5));

            return intdiv($emitidos, self::POR_MINUTO);
        } catch (\Throwable $e) {
            return intdiv($posicionLocal, self::POR_MINUTO);
        }
    }
}
