<?php

namespace Tests\Unit;

use App\Models\Grupo;
use PHPUnit\Framework\TestCase;

/**
 * La clave corta del grupo, la que se imprime en el PDF de horarios.
 */
class GrupoTest extends TestCase
{
    public static function nombres(): array
    {
        return [
            'clave y materia' => ['3KM-COSTOS EMPRESARIALES', '3KM'],
            'clave con sección' => ['1GV(a)-FÍSICA EN GASTRONOMÍA', '1GV(a)'],
            'clave sin número' => ['ESV-Álgebra Lineal', 'ESV'],
            'con espacios alrededor' => [' 5V1-TALLER DE DISEÑO III ', '5V1'],
            'espacios junto al guion' => ['1SM - Cálculo', '1SM'],
            'sin guion: completo' => ['GRUPO UNICO', 'GRUPO UNICO'],
            'lo de antes del guion no es una clave' => ['Fundamentos-1SM', 'Fundamentos-1SM'],
            'frase con guion' => ['Grupo A - tarde', 'Grupo A - tarde'],
            'vacío' => ['', ''],
        ];
    }

    /** @dataProvider nombres */
    public function test_saca_la_clave_del_nombre_del_grupo(string $nombre, string $esperada): void
    {
        $this->assertSame($esperada, (new Grupo(['nombre_grupo' => $nombre]))->clave_corta);
    }
}
