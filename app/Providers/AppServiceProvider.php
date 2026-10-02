<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use App\Providers\Exception;
use Illuminate\Pagination\Paginator; //Sirve para la paginacion
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Models\Asistencia;
use Carbon\Carbon;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //DB::connection()->getPDO();
           //dump('Database is connected. Database Name is : ' . DB::connection()->getDatabaseName());
        Paginator::useBootstrapFive();//paginacion

        // Si la instalación se publica por HTTPS (APP_URL empieza con https://),
        // todos los enlaces y formularios se generan en https.
        //
        // Detrás de un proxy o de un servidor que termina el HTTPS, Laravel no lo
        // sabe y los generaría en http://. En local, con APP_URL en http, no cambia
        // nada.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // El botón de cerrar sesión del alumno avisa cuando además va a registrar
        // su salida de uso libre, para que nadie la termine sin querer.
        View::composer('layouts.alumnos', function ($vista) {
            $alumno = Auth::user();

            $abierta = $alumno && $alumno->rol === 'Alumno'
                && Asistencia::where('user_id', $alumno->id)
                    ->where('tipo', 'Uso Libre')
                    ->whereDate('fecha_hora_registro', Carbon::today('America/Hermosillo')->toDateString())
                    ->whereNull('fecha_hora_salida')
                    ->exists();

            $vista->with('usoLibreAbierto', $abierta);
        });
    }
}
