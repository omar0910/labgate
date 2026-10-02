<?php

namespace Tests\Feature;

use App\Models\Asistencia;
use App\Models\AsistenciaProfesor;
use App\Models\Equipo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El alumno registra su asistencia a la clase que se está dando.
 */
class AsistenciaDeClaseTest extends TestCase
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

    protected function registrarse(array $datos = [], array $sesion = [])
    {
        return $this->actingAs($this->alumno)->withSession($sesion)
            ->post('/alumno/marcar-asistencia', ['horario_id' => $this->clase->id] + $datos);
    }

    public function test_desde_la_computadora_del_laboratorio_queda_anotada_esa_computadora(): void
    {
        $this->registrarse([], $this->desdeLaPc($this->laboratorio, 5))->assertSessionHas('success');

        $this->assertDatabaseHas('asistencias', [
            'user_id' => $this->alumno->id, 'horario_id' => $this->clase->id, 'tipo' => 'Clase',
            'estado' => 'presente', 'numero_maquina' => 5, 'centro_computo_id' => $this->laboratorio->id,
        ]);
    }

    public function test_sin_el_script_el_alumno_escribe_el_numero_de_su_computadora(): void
    {
        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('success');

        $this->assertDatabaseHas('asistencias', ['user_id' => $this->alumno->id, 'numero_maquina' => 3, 'equipo_personal' => 0]);
    }

    public function test_con_su_propia_laptop_no_ocupa_ninguna_computadora(): void
    {
        $this->registrarse(['equipo_personal' => 1])->assertSessionHas('success');

        $this->assertDatabaseHas('asistencias', ['user_id' => $this->alumno->id, 'numero_maquina' => null, 'equipo_personal' => 1]);
    }

    public function test_no_puede_registrarse_dos_veces_en_la_misma_clase(): void
    {
        $this->registrarse(['numero_maquina' => 3]);
        $this->registrarse(['numero_maquina' => 4])->assertSessionHas('error');

        $this->assertSame(1, Asistencia::where('user_id', $this->alumno->id)->count());
    }

    public function test_no_puede_registrarse_en_la_clase_de_otro_grupo(): void
    {
        $ajena = $this->clase($this->laboratorio('Laboratorio B'));

        $this->actingAs($this->alumno)->post('/alumno/marcar-asistencia', ['horario_id' => $ajena->id, 'numero_maquina' => 3])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_antes_de_tiempo_o_ya_terminada_la_clase_no_se_puede(): void
    {
        $this->sonLas('09:30');     // faltan más de 15 minutos
        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('error');

        $this->sonLas('12:05');     // ya terminó
        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);

        $this->sonLas('09:50');     // desde 15 minutos antes sí
        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('success');
    }

    public function test_otro_dia_de_la_semana_esa_clase_no_se_imparte(): void
    {
        $this->sonLas('2026-03-12 10:30:00');   // jueves; la clase es de los miércoles

        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_no_puede_usar_la_computadora_que_ya_ocupa_un_companero(): void
    {
        $this->registradoEnClase($this->alumnoDe($this->clase), $this->clase, 5);

        $this->registrarse(['numero_maquina' => 5])->assertSessionHas('error');

        $this->assertSame(0, Asistencia::where('user_id', $this->alumno->id)->count());
    }

    public function test_no_puede_usar_una_computadora_en_mantenimiento(): void
    {
        Equipo::where('centro_computo_id', $this->laboratorio->id)->where('numero_maquina', 5)->update(['estado' => 'mantenimiento']);

        $this->registrarse(['numero_maquina' => 5])->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_no_acepta_un_numero_de_computadora_que_el_laboratorio_no_tiene(): void
    {
        $this->registrarse(['numero_maquina' => 11])->assertSessionHasErrors('numero_maquina');
    }

    public function test_desde_una_computadora_de_otro_laboratorio_se_le_avisa(): void
    {
        $otro = $this->laboratorio('Laboratorio B');

        $this->registrarse([], $this->desdeLaPc($otro, 2))->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_si_el_profesor_no_dio_la_clase_nadie_se_registra_en_ella(): void
    {
        AsistenciaProfesor::create(['horario_id' => $this->clase->id, 'fecha' => now()->toDateString(), 'estado' => 'falta']);

        $this->registrarse(['numero_maquina' => 3])->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_si_la_lista_ya_lo_marco_presente_solo_completa_su_computadora(): void
    {
        // El profesor pasó lista antes de que el alumno se registrara: quedó
        // presente, pero sin computadora anotada.
        $this->registradoEnClase($this->alumno, $this->clase, null);

        $this->registrarse(['numero_maquina' => 7])->assertSessionHas('success');

        $this->assertSame(1, Asistencia::where('user_id', $this->alumno->id)->count());
        $this->assertDatabaseHas('asistencias', ['user_id' => $this->alumno->id, 'numero_maquina' => 7]);
    }

    public function test_cada_registro_le_suma_un_uso_a_la_computadora(): void
    {
        $this->registrarse(['numero_maquina' => 3]);

        $equipo = Equipo::where('centro_computo_id', $this->laboratorio->id)->where('numero_maquina', 3)->first();

        $this->assertSame(1, (int) $equipo->usos_acumulados);
        $this->assertSame(1, (int) $equipo->usos_historicos);
    }
}
