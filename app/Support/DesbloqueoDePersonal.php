<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * Equipos que el personal libera con sólo iniciar sesión en ellos.
 *
 * En los laboratorios con bloqueo, la computadora se queda con el sistema en
 * pantalla completa hasta que alguien se registra. Eso deja fuera al personal:
 * un técnico que necesita instalar un programa dentro del perfil de alumnos, o
 * un profesor que va a preparar su clase, no tienen ninguna entrada que
 * registrar.
 *
 * Por eso, cuando un administrador, un encargado o un profesor inicia sesión en
 * la pantalla de bloqueo, ese equipo queda liberado: el script lo ve en su
 * siguiente consulta y desbloquea, igual que si un alumno se hubiera registrado.
 *
 * El permiso dura mientras esa persona tenga su sesión abierta en el sistema. Se
 * retira cuando cierra sesión, y también al iniciar sesión en Windows de nuevo
 * (el script lo cancela al arrancar), para que un descuido no deje la máquina
 * abierta al día siguiente.
 */
class DesbloqueoDePersonal
{
    /** Quiénes pueden liberar un equipo con sólo entrar al sistema en él. */
    const ROLES = ['Administrador', 'Encargado', 'Profesor'];

    public static function puedeLiberar(?User $usuario): bool
    {
        return $usuario !== null && in_array($usuario->rol, self::ROLES, true);
    }

    public static function abrir(int $centro, int $maquina, User $usuario): void
    {
        // Sin caducidad a propósito: el equipo se queda libre mientras esa
        // persona trabaje, aunque pase una hora sin tocar el sistema.
        Cache::forever(self::clave($centro, $maquina), [
            'user_id' => $usuario->id,
            'rol'     => $usuario->rol,
            'desde'   => now()->toDateTimeString(),
        ]);
    }

    public static function cerrar(int $centro, int $maquina): void
    {
        Cache::forget(self::clave($centro, $maquina));
    }

    public static function activo(int $centro, int $maquina): bool
    {
        return Cache::has(self::clave($centro, $maquina));
    }

    protected static function clave(int $centro, int $maquina): string
    {
        return "equipo-liberado:{$centro}:{$maquina}";
    }
}
