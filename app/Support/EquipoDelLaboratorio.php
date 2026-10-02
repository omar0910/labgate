<?php

namespace App\Support;

use App\Models\CentroComputo;
use Illuminate\Http\Request;

/**
 * Desde qué computadora del laboratorio se está usando el sistema.
 *
 * Las computadoras con el script de bloqueo abren el sistema con su
 * identificación (/redirect?equipo=1-33) y RecordarEquipo la apunta en la
 * sesión. Con eso, al registrar su asistencia o su uso libre, el alumno no tiene
 * que escribir el número de PC: se pone solo, y no puede cambiarlo ni marcar
 * "equipo personal", porque se sabe que está en una PC del laboratorio.
 *
 * En una laptop (o en una PC sin el script) no hay nada apuntado: el alumno
 * escribe el número a mano o marca que usa su equipo personal.
 */
class EquipoDelLaboratorio
{
    /** Dónde lo deja RecordarEquipo en la sesión. */
    const LLAVE = 'equipo_del_laboratorio';

    /**
     * La PC desde la que se entró, o null si no es una PC del laboratorio.
     *
     * @return array{centro:int, maquina:int, laboratorio:\App\Models\CentroComputo}|null
     */
    public static function detectado(Request $request): ?array
    {
        $equipo = $request->session()->get(self::LLAVE);

        if (! is_array($equipo) || empty($equipo['centro']) || empty($equipo['maquina'])) {
            return null;
        }

        $laboratorio = CentroComputo::find($equipo['centro']);

        // Un número que no corresponde a ningún laboratorio (o a una máquina que no
        // existe) no se toma en cuenta.
        if (! $laboratorio || ($laboratorio->capacidad && $equipo['maquina'] > $laboratorio->capacidad)) {
            return null;
        }

        return [
            'centro'      => (int) $equipo['centro'],
            'maquina'     => (int) $equipo['maquina'],
            'laboratorio' => $laboratorio,
        ];
    }

    /**
     * Lo guarda de nuevo tras vaciar la sesión (al cerrar sesión). Si se perdiera,
     * el siguiente alumno que entrara en esa misma PC ya no sería detectado.
     */
    public static function conservar(Request $request, $equipo): void
    {
        if (is_array($equipo)) {
            $request->session()->put(self::LLAVE, $equipo);
        }
    }
}
