<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

trait CreatesApplication
{
    /**
     * Creates the application.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Las pruebas vacían la base en cada corrida. Si por un descuido de
        // configuración apuntaran a la base de trabajo, la borrarían: aquí se
        // detienen antes de tocar nada.
        $base = (string) $app['config']->get('database.connections.' . $app['config']->get('database.default') . '.database');

        if (! str_ends_with($base, '_test')) {
            throw new RuntimeException("Las pruebas sólo corren sobre una base cuyo nombre termine en _test (está configurada \"$base\").");
        }

        return $app;
    }
}
