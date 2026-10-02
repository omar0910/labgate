<?php

namespace Tests;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * El momento en que "ocurren" las pruebas: un miércoles a media mañana, a
     * mitad de la clase del escenario (10:00 a 12:00). Con la hora fija, las
     * pruebas dan lo mismo se corran cuando se corran.
     */
    const AHORA = '2026-03-11 10:30:00';

    protected function setUp(): void
    {
        parent::setUp();

        // Las vistas no necesitan los estilos compilados para probarse
        $this->withoutVite();

        $this->sonLas(self::AHORA);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Mueve el reloj de la prueba: sonLas('12:20') o sonLas('2026-03-12 09:00'). */
    protected function sonLas(string $momento): void
    {
        if (strlen($momento) <= 8) {
            $momento = substr(self::AHORA, 0, 10) . ' ' . $momento;
        }

        Carbon::setTestNow(Carbon::parse($momento, config('app.timezone')));
    }
}
