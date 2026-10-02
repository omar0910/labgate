<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Apunta en la sesión desde qué computadora del laboratorio se está entrando.
 *
 * Las computadoras con bloqueo abren el sistema con su identificación en la
 * dirección: /redirect?equipo=1-33 (laboratorio 1, máquina 33). Aquí se guarda
 * en la sesión para que, al iniciar sesión, el sistema sepa qué equipo liberar
 * si quien entra es del personal.
 *
 * A los alumnos les pone solo el número de PC al registrar su asistencia o su
 * uso libre (ver App\Support\EquipoDelLaboratorio).
 */
class RecordarEquipo
{
    public function handle(Request $request, Closure $next): Response
    {
        $equipo = $request->query('equipo');

        if (is_string($equipo) && preg_match('/^(\d{1,4})-(\d{1,4})$/', $equipo, $partes)) {
            $request->session()->put('equipo_del_laboratorio', [
                'centro'  => (int) $partes[1],
                'maquina' => (int) $partes[2],
            ]);

            // Se guarda en el acto. La pantalla de bloqueo entra por /redirect, y
            // ahí Laravel corta la petición para mandar al login: sin este save,
            // el dato se perdería antes de llegar a escribirse.
            $request->session()->save();
        }

        return $next($request);
    }
}
