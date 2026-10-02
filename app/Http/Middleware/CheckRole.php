<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  ...$roles  <-- Los roles permitidos (ej: 'Administrador', o 'role:Administrador,Encargado')
     * @return mixed
     */
    public function handle(Request $request, Closure $next, ...$roles)
    {
        // 1. Verificar si está logueado
        if (!Auth::check()) {
            return redirect('/login');
        }

        // 2. Obtener el usuario actual
        $user = Auth::user();

        // 3. Verificar si su rol es uno de los permitidos para esta ruta. Acepta
        //    varios (role:Administrador,Encargado): el Monitor y la Bitácora usaban
        //    una función en línea para eso, y con ella "php artisan route:cache"
        //    generaba un archivo roto que tiraba todo el sistema.
        if (in_array($user->rol, $roles, true)) {
            return $next($request); // ¡Pase usted!
        }

        // 4. Si NO coincide, lo expulsamos a su propio dashboard
        // Esto evita que vean una pantalla de error fea, mejor los mandamos a "su casa"
        switch ($user->rol) {
            case 'Administrador':
                return redirect()->route('admin.dashboard');
            case 'Encargado':
                return redirect()->route('encargado.inicio');
            case 'Profesor':
                return redirect()->route('profesor.dashboard'); // Asegúrate de tener esta ruta
            case 'Alumno':
                return redirect()->route('alumno.dashboard');   // Asegúrate de tener esta ruta
        }

        // Si el rol no existe o es raro, cerramos sesión por seguridad
        return redirect('/login')->with('error', 'Rol no autorizado.');
    }
}
