<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Limpia los filtros de las direcciones (?fecha=..., ?search=...) antes de que
 * lleguen a las pantallas.
 *
 * Varias pantallas daban error 500 con una dirección escrita a mano: una lista
 * donde va un solo valor (?search[]=x, ?semestre_id[]=1) o una fecha imposible
 * (?fecha=abc, ?fecha_inicio=2026-13-45). Ningún formulario GET del sistema
 * manda listas (los que sí, como clases[] o equipos[], son POST), y todos los
 * filtros de fecha son AAAA-MM-DD, así que esos valores se descartan y la
 * pantalla usa lo que usaría sin el filtro (hoy, el semestre activo…).
 */
class LimpiarFiltros
{
    /** Filtros que las pantallas esperan como fecha AAAA-MM-DD. */
    const FECHAS = ['fecha', 'fecha_inicio', 'fecha_fin', 'desde', 'hasta', 'semana', 'dia'];

    public function handle(Request $request, Closure $next)
    {
        if ($request->isMethod('GET') || $request->isMethod('HEAD')) {
            foreach ($request->query->all() as $clave => $valor) {
                $sobra = is_array($valor)
                    || (in_array($clave, self::FECHAS, true) && $valor !== null && $valor !== '' && ! self::esFecha($valor));

                if ($sobra) {
                    $request->query->remove($clave);
                }
            }
        }

        return $next($request);
    }

    /** ¿Es una fecha real AAAA-MM-DD? */
    public static function esFecha($valor): bool
    {
        return is_string($valor)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $valor, $partes) === 1
            && checkdate((int) $partes[2], (int) $partes[3], (int) $partes[1]);
    }
}
