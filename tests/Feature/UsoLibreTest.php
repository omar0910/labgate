<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\Equipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El uso libre: el alumno ocupa una computadora fuera de sus clases.
 */
class UsoLibreTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected $laboratorio;
    protected $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semestre();
        $this->laboratorio = $this->laboratorio();
        $this->alumno = $this->usuario('Alumno');
    }

    protected function entrar(int $pc, $laboratorio = null, array $sesion = [])
    {
        return $this->actingAs($this->alumno)->withSession($sesion)->post('/alumno/registrar-uso-libre', [
            'centro_computo_id' => ($laboratorio ?? $this->laboratorio)->id,
            'numero_maquina' => $pc,
        ]);
    }

    protected function sesionAbierta(): ?Asistencia
    {
        return Asistencia::where('user_id', $this->alumno->id)->where('tipo', 'Uso Libre')->whereNull('fecha_hora_salida')->first();
    }

    public function test_el_alumno_registra_su_entrada_y_la_computadora_queda_ocupada(): void
    {
        $this->entrar(4)->assertRedirect('/alumno/dashboard')->assertSessionHas('success');

        $this->assertNotNull($this->sesionAbierta());
        $this->assertTrue(Asistencia::estaOcupada($this->laboratorio->id, 4));
    }

    public function test_un_laboratorio_sin_uso_libre_no_lo_permite(): void
    {
        $soloClases = $this->laboratorio('Laboratorio de redes', 10, false);

        $this->entrar(4, $soloClases)->assertSessionHas('error');

        $this->assertNull($this->sesionAbierta());
    }

    public function test_no_puede_ocupar_la_computadora_de_otro(): void
    {
        $this->enUsoLibre($this->usuario('Alumno'), $this->laboratorio, 4);

        $this->entrar(4)->assertSessionHas('error');

        $this->assertNull($this->sesionAbierta());
    }

    public function test_no_puede_ocupar_una_computadora_en_mantenimiento_ni_una_que_no_existe(): void
    {
        Equipo::where('centro_computo_id', $this->laboratorio->id)->where('numero_maquina', 4)->update(['estado' => 'mantenimiento']);

        $this->entrar(4)->assertSessionHas('error');
        $this->entrar(99)->assertSessionHas('error');

        $this->assertNull($this->sesionAbierta());
    }

    public function test_no_puede_tener_dos_sesiones_a_la_vez(): void
    {
        $this->entrar(4);
        $this->entrar(5)->assertSessionHas('error');

        $this->assertSame(1, Asistencia::where('user_id', $this->alumno->id)->count());
    }

    public function test_al_terminar_queda_la_hora_de_salida_y_la_computadora_libre(): void
    {
        $this->entrar(4);
        $this->sonLas('11:15');

        $this->actingAs($this->alumno)->post('/alumno/terminar-uso-libre')->assertSessionHas('success');

        $this->assertNull($this->sesionAbierta());
        $this->assertFalse(Asistencia::estaOcupada($this->laboratorio->id, 4));
        $this->assertStringContainsString('11:15', (string) Asistencia::where('user_id', $this->alumno->id)->value('fecha_hora_salida'));
    }

    public function test_cerrar_sesion_en_el_sistema_tambien_registra_su_salida(): void
    {
        $this->entrar(4);

        $this->actingAs($this->alumno)->post('/logout');

        $this->assertNull($this->sesionAbierta());
        $this->assertGuest();
    }

    public function test_donde_el_uso_libre_es_solo_en_sus_computadoras_no_vale_registrarse_con_laptop(): void
    {
        $estricto = $this->laboratorio('Laboratorio C', 10, true, ['uso_libre_solo_en_sus_pcs' => true]);

        // Escribiendo los datos a mano, sin estar en una de sus computadoras
        $this->entrar(4, $estricto)->assertSessionHas('error');
        $this->assertNull($this->sesionAbierta());

        // Desde la computadora 4 de ese laboratorio
        $this->entrar(4, $estricto, $this->desdeLaPc($estricto, 4))->assertSessionHas('success');
        $this->assertNotNull($this->sesionAbierta());
    }

    public function test_fuera_del_semestre_no_se_registra_nada(): void
    {
        $this->sonLas('2026-07-15 10:30:00');

        $this->entrar(4)->assertSessionHas('error');

        $this->assertNull($this->sesionAbierta());
    }
}
