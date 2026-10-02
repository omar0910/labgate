<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * Lo que el servidor le contesta a cada computadora del laboratorio.
 *
 * El script instalado en la computadora pregunta cada pocos segundos si tiene
 * una sesión activa: mientras la respuesta sea "ocupada", se queda desbloqueada;
 * si no, muestra la pantalla de acceso y nada más.
 */
class BloqueoDeEquiposTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected $laboratorio;
    protected $clase;
    protected $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->laboratorio = $this->laboratorio();
        $this->clase = $this->clase($this->laboratorio);
        $this->alumno = $this->alumnoDe($this->clase);
    }

    /** Lo que contesta el servidor a la computadora número $pc. */
    protected function estado(int $pc, bool $acabaDeEncender = false): array
    {
        return $this->getJson("/api/equipo/estado?centro={$this->laboratorio->id}&maquina=$pc" . ($acabaDeEncender ? '&reiniciar=1' : ''))
            ->assertOk()->json();
    }

    public function test_sin_nadie_registrado_la_computadora_esta_bloqueada(): void
    {
        $this->assertSame(['ocupada' => false, 'tipo' => null], $this->estado(5));
    }

    public function test_la_consulta_exige_laboratorio_y_computadora(): void
    {
        $this->getJson('/api/equipo/estado')->assertStatus(422);
        $this->getJson('/api/equipo/estado?centro=abc&maquina=-1')->assertStatus(422);
    }

    public function test_la_respuesta_no_revela_ningun_dato_del_alumno(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5);

        $this->assertSame(['ocupada', 'tipo'], array_keys($this->estado(5)));
    }

    public function test_el_registro_de_clase_desbloquea_solo_esa_computadora(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5);

        $this->assertSame(['ocupada' => true, 'tipo' => 'Clase'], $this->estado(5));
        $this->assertFalse($this->estado(6)['ocupada']);
    }

    public function test_al_terminar_la_clase_se_bloquea_tras_quince_minutos_de_margen(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5);

        $this->sonLas('12:10');
        $this->assertTrue($this->estado(5)['ocupada'], 'A los 10 minutos de terminar sigue libre');

        $this->sonLas('12:20');
        $this->assertFalse($this->estado(5)['ocupada'], 'Pasado el margen se bloquea');
    }

    public function test_una_falta_no_desbloquea_nada(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5, ['estado' => 'falta']);

        $this->assertFalse($this->estado(5)['ocupada']);
    }

    public function test_si_se_va_la_luz_a_media_clase_al_encender_sigue_desbloqueada(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5);
        $this->estado(5);

        $this->sonLas('10:50');

        $this->assertSame(['ocupada' => true, 'tipo' => 'Clase'], $this->estado(5, acabaDeEncender: true));
    }

    public function test_si_el_encargado_fuerza_la_salida_la_computadora_de_clase_se_bloquea(): void
    {
        $this->registradoEnClase($this->alumno, $this->clase, 5);

        $this->actingAs($this->usuario('Encargado'))
            ->post("/monitor/vivo/{$this->laboratorio->id}/pc/5/cerrar")
            ->assertSessionHas('success');

        $this->assertFalse($this->estado(5)['ocupada']);
    }

    public function test_el_uso_libre_desbloquea_hasta_que_el_alumno_lo_termina(): void
    {
        $sesion = $this->enUsoLibre($this->alumno, $this->laboratorio, 7);

        $this->assertSame(['ocupada' => true, 'tipo' => 'Uso Libre'], $this->estado(7));

        $sesion->update(['fecha_hora_salida' => now()]);

        $this->assertFalse($this->estado(7)['ocupada']);
    }

    public function test_al_encender_de_nuevo_se_cierra_el_uso_libre_que_quedo_abierto(): void
    {
        $sesion = $this->enUsoLibre($this->alumno, $this->laboratorio, 7);
        $this->estado(7);                       // 10:30, la computadora se reporta por última vez

        $this->sonLas('10:40');                 // alguien la vuelve a encender

        $this->assertFalse($this->estado(7, acabaDeEncender: true)['ocupada']);
        // La salida es la hora del último reporte: cuando se apagó
        $this->assertStringContainsString('10:30', (string) $sesion->fresh()->fecha_hora_salida);
    }

    public function test_una_computadora_que_deja_de_reportarse_diez_minutos_se_da_por_apagada(): void
    {
        $sesion = $this->enUsoLibre($this->alumno, $this->laboratorio, 7);
        $this->estado(7);                       // último reporte a las 10:30

        $this->sonLas('10:35');
        $this->artisan('equipos:cerrar-uso-libre-apagados');
        $this->assertNull($sesion->fresh()->fecha_hora_salida, 'A los 5 minutos todavía no');

        $this->sonLas('10:45');
        $this->artisan('equipos:cerrar-uso-libre-apagados');
        $this->assertStringContainsString('10:30', (string) $sesion->fresh()->fecha_hora_salida);
    }

    public function test_si_solo_fue_un_corte_de_red_el_uso_libre_se_reabre(): void
    {
        $sesion = $this->enUsoLibre($this->alumno, $this->laboratorio, 7);
        $this->estado(7);

        $this->sonLas('10:45');
        $this->artisan('equipos:cerrar-uso-libre-apagados');
        $this->assertNotNull($sesion->fresh()->fecha_hora_salida);

        // Vuelve la red y la computadora se reporta SIN haber reiniciado
        $this->sonLas('10:50');
        $this->assertTrue($this->estado(7)['ocupada']);
        $this->assertNull($sesion->fresh()->fecha_hora_salida);
    }

    public function test_el_personal_libera_la_computadora_con_solo_iniciar_sesion_en_ella(): void
    {
        $profesor = $this->usuario('Profesor');

        $this->withSession($this->desdeLaPc($this->laboratorio, 9))
            ->post('/login', ['login' => $profesor->username, 'password' => $this->contrasena]);

        $this->assertSame(['ocupada' => true, 'tipo' => 'Personal'], $this->estado(9));

        $this->post('/logout');

        $this->assertFalse($this->estado(9)['ocupada']);
    }

    public function test_un_alumno_no_libera_la_computadora_con_solo_iniciar_sesion(): void
    {
        $this->withSession($this->desdeLaPc($this->laboratorio, 9))
            ->post('/login', ['login' => $this->alumno->username, 'password' => $this->contrasena]);

        $this->assertFalse($this->estado(9)['ocupada']);
    }
}
