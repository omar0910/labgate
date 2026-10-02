<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lo que no se permite en la demostración pública.
 *
 * Casi todo queda abierto para que se pueda probar de verdad (registrar
 * asistencia, pasar lista, reportar una falla, crear clases...), y los datos se
 * reinician cada noche. Sólo se cierra lo que dejaría la demostración inservible
 * para el siguiente visitante, o lo que no conviene exponer en un sitio público.
 *
 * Con DEMO=false no hace nada.
 */
class ModoDemo
{
    /** Acciones cerradas para todos (nombres de ruta; * es comodín). */
    const CERRADAS = [
        // Respaldos: descargarían o reemplazarían la base completa
        'backup.exportar', 'backup.importar',
        // Subir archivos: importadores de Excel y foto de perfil
        '*.importar', '*.importar.*', 'grupos.importar-excel',
        // Las cuentas son compartidas: nadie les cambia la contraseña ni los datos
        'profile.update', 'profile.update-password', 'profile.photo.delete',
        // Sin semestre activo o sin un laboratorio, todo lo demás se queda vacío
        'semestres.store', 'semestres.update', 'semestres.destroy', 'centros-computo.destroy',
    ];

    /** Acciones que no se permiten sobre las cuatro cuentas de demostración. */
    const SOBRE_LAS_CUENTAS = [
        'usuarios.update', 'usuarios.destroy', 'alumnos.update', 'alumnos.destroy',
        'admin.profesores.update', 'admin.profesores.destroy',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.activo')) {
            return $next($request);
        }

        $ruta = (string) $request->route()?->getName();

        if (Str::is(self::CERRADAS, $ruta) || ($this->esSobreUnaCuentaDeDemostracion($request, $ruta))) {
            $aviso = 'Esa acción está deshabilitada en la demostración pública. En una instalación real funciona con normalidad.';

            return $request->expectsJson()
                ? response()->json(['message' => $aviso], 403)
                : redirect()->back()->with('aviso_demo', $aviso);
        }

        $respuesta = $next($request);

        // La demostración no debe salir en los buscadores
        $respuesta->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $respuesta;
    }

    protected function esSobreUnaCuentaDeDemostracion(Request $request, string $ruta): bool
    {
        if (! in_array($ruta, self::SOBRE_LAS_CUENTAS, true)) {
            return false;
        }

        $protegidas = User::whereIn('username', config('demo.cuentas'))->pluck('id')->all();

        foreach ($request->route()->parameters() as $valor) {
            $id = is_object($valor) ? ($valor->id ?? null) : $valor;

            if (is_numeric($id) && in_array((int) $id, $protegidas, true)) {
                return true;
            }
        }

        return false;
    }
}
