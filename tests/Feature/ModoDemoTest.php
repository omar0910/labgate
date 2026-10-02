<?php

namespace Tests\Feature;

use App\Demo\Generador;
use App\Demo\Simulador;
use App\Models\Asistencia;
use App\Models\CentroComputo;
use App\Models\Equipo;
use App\Models\Horario;
use App\Models\Semestre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El modo demostración: lo que agrega con DEMO=true y que con DEMO=false no existe.
 */
class ModoDemoTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected function encender(): void
    {
        config(['demo.activo' => true]);
    }

    public function test_apagado_no_hay_acceso_con_un_clic(): void
    {
        $this->usuario('Administrador', ['username' => 'admin.demo']);

        $this->get('/login')->assertOk()->assertDontSee('demo/entrar');
        $this->post('/demo/entrar/Administrador')->assertNotFound();
        $this->assertGuest();
    }

    public function test_apagado_los_comandos_de_la_demostracion_se_niegan_a_correr(): void
    {
        $alguien = $this->usuario('Alumno');

        $this->artisan('demo:reiniciar')->assertFailed();
        $this->artisan('demo:simular')->assertFailed();

        // La base sigue intacta
        $this->assertNotNull($alguien->fresh());
    }

    public function test_encendido_se_entra_con_un_clic_como_cualquiera_de_los_roles(): void
    {
        $this->encender();
        $this->get('/login')->assertOk()->assertSee('demo/entrar/Administrador')->assertSee('demo/entrar/Alumno');

        foreach (config('demo.cuentas') as $rol => $usuario) {
            $cuenta = $this->usuario($rol, ['username' => $usuario]);

            $this->post("/demo/entrar/$rol")->assertRedirect('/redirect');
            $this->assertAuthenticatedAs($cuenta);

            $this->post('/logout');
        }

        $this->post('/demo/entrar/Director')->assertNotFound();
    }

    public function test_encendido_bloquea_lo_que_dejaria_la_demostracion_inservible(): void
    {
        $this->encender();
        $admin = $this->usuario('Administrador', ['username' => 'admin.demo']);
        $encargado = $this->usuario('Encargado', ['username' => 'encargado.demo']);
        $laboratorio = $this->laboratorio();
        $semestre = $this->semestre();

        $intentos = [
            ['get', '/admin/mantenimiento/backup/exportar', []],
            ['post', '/admin/mantenimiento/backup/importar', []],
            ['post', '/admin/alumnos/importar', []],
            ['put', '/perfil/password', ['current_password' => $this->contrasena, 'new_password' => 'OtraClave#2026', 'new_password_confirmation' => 'OtraClave#2026']],
            ['delete', "/admin/semestres/{$semestre->id}", []],
            ['delete', "/admin/centros-computo/{$laboratorio->id}", []],
            ['delete', "/admin/usuarios/{$encargado->id}", []],
        ];

        foreach ($intentos as [$metodo, $url, $datos]) {
            $this->actingAs($admin)->$metodo($url, $datos)->assertSessionHas('aviso_demo');
        }

        $this->assertTrue(Hash::check($this->contrasena, $admin->fresh()->password), 'La contraseña no cambió');
        $this->assertTrue((bool) $encargado->fresh()->activo, 'La cuenta de demostración sigue activa');
        $this->assertNotNull(Semestre::find($semestre->id));
        $this->assertNotNull(CentroComputo::find($laboratorio->id));
    }

    public function test_encendido_lo_demas_si_se_puede_probar(): void
    {
        $this->encender();
        $admin = $this->usuario('Administrador', ['username' => 'admin.demo']);
        $otro = $this->usuario('Encargado');

        $this->actingAs($admin)->delete("/admin/usuarios/{$otro->id}")->assertSessionMissing('aviso_demo');

        $this->assertFalse((bool) $otro->fresh()->activo);
    }

    public function test_encendido_pide_a_los_buscadores_no_indexar_el_sitio(): void
    {
        $this->get('/login')->assertHeaderMissing('X-Robots-Tag');

        $this->encender();

        $this->get('/login')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_los_datos_ficticios_salen_completos_y_sin_choques_de_horario(): void
    {
        $resumen = (new Generador())->generar();

        $this->assertSame(4, $resumen['Laboratorios']);
        $this->assertGreaterThan(250, $resumen['Alumnos']);
        $this->assertGreaterThan(5000, $resumen['Registros de asistencia']);

        // Las cuatro cuentas de demostración existen y entran con la contraseña publicada
        foreach (config('demo.cuentas') as $rol => $usuario) {
            $cuenta = User::where('username', $usuario)->first();
            $this->assertSame($rol, $cuenta?->rol);
            $this->assertTrue(Hash::check(config('demo.contrasena'), $cuenta->password));
        }

        // El semestre está en curso: hoy cae dentro
        $this->assertTrue(Semestre::activo()->contieneFecha(now()));

        // Ningún laboratorio ni profesor tiene dos clases a la misma hora
        $clases = Horario::all();
        foreach ($clases as $a) {
            foreach ($clases as $b) {
                $seEnciman = $a->id < $b->id && $a->dia_semana === $b->dia_semana
                    && $a->hora_inicio < $b->hora_fin && $b->hora_inicio < $a->hora_fin;

                $this->assertFalse($seEnciman && $a->centro_computo_id === $b->centro_computo_id, "Choque de laboratorio entre #{$a->id} y #{$b->id}");
                $this->assertFalse($seEnciman && $a->user_id === $b->user_id, "Choque de profesor entre #{$a->id} y #{$b->id}");
            }
        }

        // Las cuentas de demostración tienen clase de lunes a viernes
        foreach (['Profesor', 'Alumno'] as $rol) {
            $cuenta = User::where('username', config("demo.cuentas.$rol"))->first();
            $dias = $rol === 'Profesor'
                ? Horario::where('user_id', $cuenta->id)->distinct()->pluck('dia_semana')
                : Horario::whereIn('grupo_id', $cuenta->grupos()->pluck('grupos.id'))->distinct()->pluck('dia_semana');

            $this->assertCount(5, $dias, "$rol de demostración: clases en {$dias->implode(', ')}");
        }

        // Nadie tiene dos registros de la misma clase el mismo día
        $duplicados = DB::table('asistencias')->where('tipo', 'Clase')
            ->select('user_id', 'horario_id', 'fecha')->groupBy('user_id', 'horario_id', 'fecha')
            ->havingRaw('COUNT(*) > 1')->get();
        $this->assertCount(0, $duplicados);

        // Los contadores de las computadoras cuadran con los registros
        $this->assertSame(Asistencia::whereNotNull('numero_maquina')->count(), (int) Equipo::sum('usos_historicos'));
    }

    public function test_el_simulador_registra_las_clases_en_curso_una_sola_vez(): void
    {
        $this->encender();
        (new Generador())->generar();

        $primera = Simulador::avanzar();
        $segunda = Simulador::avanzar();

        // Miércoles a las 10:30: hay clases que ya empezaron
        $this->assertGreaterThan(0, $primera['clases']);
        $this->assertSame(0, $segunda['clases'], 'Correrlo otra vez no vuelve a registrar las mismas clases');

        // El alumno de demostración queda libre mientras dura su clase, para que
        // quien entre con su cuenta pueda registrarse él mismo.
        $alumno = User::where('username', config('demo.cuentas.Alumno'))->first();
        $enCurso = Horario::whereIn('grupo_id', $alumno->grupos()->pluck('grupos.id'))->queTocanEl(now())
            ->where('hora_inicio', '<=', now()->format('H:i:s'))->where('hora_fin', '>', now()->format('H:i:s'))->pluck('id');

        $this->assertSame(0, Asistencia::where('user_id', $alumno->id)->whereIn('horario_id', $enCurso)->whereDate('fecha', now())->count());
    }
}
