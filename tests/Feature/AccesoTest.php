<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * Entrar al sistema: con qué se entra, a dónde llega cada rol y qué zonas le
 * quedan cerradas.
 */
class AccesoTest extends TestCase
{
    use RefreshDatabase, Escenario;

    public static function panelDeCadaRol(): array
    {
        return [
            'administrador' => ['Administrador', '/admin/dashboard'],
            'encargado' => ['Encargado', '/encargado/inicio'],
            'profesor' => ['Profesor', '/profesor/dashboard'],
            'alumno' => ['Alumno', '/alumno/dashboard'],
        ];
    }

    /** @dataProvider panelDeCadaRol */
    public function test_cada_rol_entra_y_llega_a_su_panel(string $rol, string $panel): void
    {
        $usuario = $this->usuario($rol);

        $this->post('/login', ['login' => $usuario->username, 'password' => $this->contrasena])
            ->assertRedirect($panel);

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_el_alumno_tambien_entra_con_su_matricula(): void
    {
        $alumno = $this->usuario('Alumno');

        $this->post('/login', ['login' => $alumno->matricula, 'password' => $this->contrasena])
            ->assertRedirect('/alumno/dashboard');

        $this->assertAuthenticatedAs($alumno);
    }

    public function test_con_la_contrasena_equivocada_no_entra(): void
    {
        $alumno = $this->usuario('Alumno');

        $this->post('/login', ['login' => $alumno->username, 'password' => 'otra-cosa'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_una_cuenta_dada_de_baja_no_entra(): void
    {
        $alumno = $this->usuario('Alumno', ['activo' => false]);

        $respuesta = $this->post('/login', ['login' => $alumno->username, 'password' => $this->contrasena]);

        $respuesta->assertSessionHasErrors('login');
        $this->assertStringContainsString('dada de baja', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_tras_cinco_intentos_fallidos_la_cuenta_se_bloquea_un_momento(): void
    {
        $alumno = $this->usuario('Alumno');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $alumno->username, 'password' => 'mala']);
        }

        // Ni con la contraseña correcta: hay que esperar
        $this->post('/login', ['login' => $alumno->username, 'password' => $this->contrasena]);

        $this->assertStringContainsString('Demasiados intentos', session('errors')->first('login'));
        $this->assertGuest();
    }

    public function test_sin_sesion_cualquier_panel_manda_a_la_pantalla_de_acceso(): void
    {
        foreach (['/admin/dashboard', '/encargado/inicio', '/profesor/dashboard', '/alumno/dashboard', '/monitor/vivo'] as $panel) {
            $this->get($panel)->assertRedirect('/login');
        }
    }

    public function test_un_alumno_no_entra_a_las_zonas_de_los_demas_roles(): void
    {
        $alumno = $this->usuario('Alumno');

        foreach (['/admin/dashboard', '/admin/alumnos', '/encargado/inicio', '/profesor/dashboard', '/monitor/vivo', '/bitacora'] as $zona) {
            $this->actingAs($alumno)->get($zona)->assertRedirect('/alumno/dashboard');
        }
    }

    public function test_el_monitor_es_del_administrador_y_del_encargado_pero_no_del_profesor(): void
    {
        $this->laboratorio();

        $this->actingAs($this->usuario('Administrador'))->get('/monitor/vivo')->assertOk();
        $this->actingAs($this->usuario('Encargado'))->get('/monitor/vivo')->assertOk();
        $this->actingAs($this->usuario('Profesor'))->get('/monitor/vivo')->assertRedirect('/profesor/dashboard');
    }

    public function test_quien_se_da_de_baja_con_la_sesion_abierta_queda_fuera_al_instante(): void
    {
        $alumno = $this->usuario('Alumno');
        $this->semestre();

        $this->actingAs($alumno)->get('/alumno/dashboard')->assertOk();

        $alumno->darDeBaja();

        $this->get('/alumno/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }
}
