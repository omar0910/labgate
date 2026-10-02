<?php

namespace App\Http\Middleware;

use App\Support\DesbloqueoDePersonal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Saca del sistema a quien fue dado de baja mientras tenía la sesión abierta.
 *
 * El inicio de sesión ya no deja entrar a una cuenta dada de baja, pero alguien
 * que ya estaba dentro seguiría trabajando hasta cerrar sesión; con esto, en su
 * siguiente clic vuelve a la pantalla de acceso.
 */
class CuentaActiva
{
    public function handle(Request $request, Closure $next)
    {
        $usuario = Auth::user();

        if ($usuario && ! $usuario->activo) {
            // Si había liberado una computadora del laboratorio, vuelve a bloquearse
            $equipo = $request->session()->get('equipo_liberado');
            if (is_array($equipo)) {
                DesbloqueoDePersonal::cerrar($equipo['centro'], $equipo['maquina']);
            }

            // La PC del laboratorio se conserva para el siguiente que entre en ella
            $equipoDelLaboratorio = $request->session()->get(\App\Support\EquipoDelLaboratorio::LLAVE);

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            \App\Support\EquipoDelLaboratorio::conservar($request, $equipoDelLaboratorio);

            return redirect()->route('login')->withErrors([
                'login' => 'Tu cuenta está dada de baja. Si crees que es un error, acude al centro de cómputo.',
            ]);
        }

        return $next($request);
    }
}
