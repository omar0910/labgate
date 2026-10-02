<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Busqueda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El buscador de los listados: por palabras sueltas, en cualquier orden, sin
 * importar acentos y perdonando un error de dedo.
 */
class BusquedaTest extends TestCase
{
    use RefreshDatabase, Escenario;

    const COLUMNAS = ['matricula', 'name', 'apellido_paterno', 'apellido_materno'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario('Alumno', ['name' => 'ROCÍO', 'apellido_paterno' => 'AMADOR', 'apellido_materno' => 'LUNA', 'matricula' => '263100001']);
        $this->usuario('Alumno', ['name' => 'JOSÉ LUIS', 'apellido_paterno' => 'PEÑA', 'apellido_materno' => 'AMADOR', 'matricula' => '263100002']);
        $this->usuario('Alumno', ['name' => 'ROCÍO', 'apellido_paterno' => 'ZAVALA', 'apellido_materno' => 'RÍOS', 'matricula' => '263100003']);
    }

    /** Las matrículas que encuentra una búsqueda. */
    protected function buscar(string $texto): array
    {
        return Busqueda::todos(fn() => User::where('rol', 'Alumno')->orderBy('matricula'), $texto, self::COLUMNAS)
            ->pluck('matricula')->all();
    }

    public function test_encuentra_nombre_y_apellido_aunque_esten_en_columnas_distintas(): void
    {
        $this->assertSame(['263100001'], $this->buscar('ROCIO AMADOR'));
    }

    public function test_el_orden_de_las_palabras_no_importa(): void
    {
        $this->assertSame(['263100001'], $this->buscar('amador luna rocio'));
    }

    public function test_no_importan_los_acentos_ni_las_mayusculas(): void
    {
        $this->assertSame(['263100002'], $this->buscar('jose pena'));
        $this->assertSame(['263100003'], $this->buscar('Ríos'));
    }

    public function test_todas_las_palabras_deben_coincidir(): void
    {
        $this->assertSame(['263100001', '263100003'], $this->buscar('rocio'));
        $this->assertSame([], $this->buscar('rocio peña'));
    }

    public function test_busca_por_matricula(): void
    {
        $this->assertSame(['263100002'], $this->buscar('263100002'));
    }

    public function test_perdona_un_error_de_dedo_al_final_de_una_palabra_larga(): void
    {
        $this->assertSame(['263100003'], $this->buscar('ZAVALX'));
    }

    public function test_sin_texto_devuelve_todo_y_los_espacios_de_mas_no_estorban(): void
    {
        $this->assertCount(3, $this->buscar(''));
        $this->assertSame(['263100001'], $this->buscar('   rocio    amador  '));
    }
}
