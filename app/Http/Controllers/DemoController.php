<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrar a la demostración con un clic, como cualquiera de los cuatro roles.
 *
 * Sólo existe con el modo demostración encendido (DEMO=true); en una instalación
 * real la dirección responde 404, igual que si no existiera.
 */
class DemoController extends Controller
{
    public function entrar(Request $request, string $rol)
    {
        abort_unless(config('demo.activo'), 404);

        $usuario = config('demo.cuentas.' . $rol);
        $cuenta = $usuario ? User::where('username', $usuario)->where('activo', true)->first() : null;

        abort_unless($cuenta, 404);

        Auth::login($cuenta);
        $request->session()->regenerate();

        return redirect()->route('dashboard_redirect');
    }
}
