<?php

namespace Tests\Feature;

use App\Models\Equipo;
use App\Models\Incidencia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * Fallas reportadas, mantenimiento y el contador de usos de cada computadora.
 */
class MantenimientoTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected $laboratorio;
    protected $encargado;
    protected $alumno;

    protected function setUp(): void
    {
        parent::setUp();

        $this->semestre();
        $this->laboratorio = $this->laboratorio();
        $this->encargado = $this->usuario('Encargado');
        $this->alumno = $this->usuario('Alumno');
    }

    protected function equipo(int $numero): Equipo
    {
        return Equipo::where('centro_computo_id', $this->laboratorio->id)->where('numero_maquina', $numero)->first();
    }

    protected function mandarAMantenimiento(int $pc)
    {
        return $this->actingAs($this->encargado)->post('/monitor/mantenimiento', [
            'centro_computo_id' => $this->laboratorio->id, 'numero_maquina' => $pc,
        ]);
    }

    public function test_al_crear_un_laboratorio_se_dan_de_alta_sus_computadoras(): void
    {
        $this->assertSame(range(1, 10), Equipo::where('centro_computo_id', $this->laboratorio->id)->orderBy('numero_maquina')->pluck('numero_maquina')->all());

        // Si crece, se agregan las que faltan
        $this->laboratorio->update(['capacidad' => 12]);

        $this->assertSame(12, Equipo::where('centro_computo_id', $this->laboratorio->id)->count());
    }

    public function test_el_reporte_de_un_alumno_no_bloquea_la_computadora(): void
    {
        $this->actingAs($this->alumno)->post('/alumno/reportar-incidencia', [
            'centro_computo_id' => $this->laboratorio->id, 'numero_maquina' => 2, 'categoria' => 'Mouse', 'descripcion' => 'No responde',
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('incidencias', ['numero_maquina' => 2, 'categoria' => 'Mouse', 'estado' => 'pendiente', 'user_id' => $this->alumno->id]);
        $this->assertSame('disponible', $this->equipo(2)->estado);
    }

    public function test_mandarla_a_mantenimiento_la_bloquea_y_abre_un_ticket(): void
    {
        $this->mandarAMantenimiento(2)->assertSessionHas('success');

        $this->assertSame('mantenimiento', $this->equipo(2)->estado);
        $this->assertDatabaseHas('incidencias', ['numero_maquina' => 2, 'estado' => 'pendiente', 'categoria' => 'Mantenimiento Preventivo (Manual)']);

        // Nadie puede registrarse en ella
        $this->actingAs($this->alumno)->post('/alumno/registrar-uso-libre', ['centro_computo_id' => $this->laboratorio->id, 'numero_maquina' => 2])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_mandarla_dos_veces_no_abre_dos_tickets(): void
    {
        $this->mandarAMantenimiento(2);
        $this->mandarAMantenimiento(2);

        $this->assertSame(1, Incidencia::count());
    }

    public function test_cerrar_el_mantenimiento_libera_la_computadora_y_reinicia_su_contador(): void
    {
        $this->equipo(2)->update(['usos_acumulados' => 40, 'usos_historicos' => 120]);
        $this->mandarAMantenimiento(2);
        $ticket = Incidencia::first();
        $admin = $this->usuario('Administrador');

        $this->actingAs($admin)->put("/admin/incidencias/{$ticket->id}/resolver", ['nota_resolucion' => 'Limpieza general'])
            ->assertSessionHas('success');

        $equipo = $this->equipo(2);
        $this->assertSame('disponible', $equipo->estado);
        $this->assertSame(0, (int) $equipo->usos_acumulados);
        $this->assertSame(120, (int) $equipo->usos_historicos, 'El histórico no se pierde');
        $this->assertNotNull($equipo->ultimo_mantenimiento);

        // Queda anotado quién lo resolvió y cuándo
        $ticket->refresh();
        $this->assertSame('resuelta', $ticket->estado);
        $this->assertSame($admin->id, (int) $ticket->resuelta_por);
        $this->assertNotNull($ticket->fecha_resolucion);
    }

    public function test_cerrar_una_falla_menor_no_reinicia_el_contador(): void
    {
        $this->equipo(2)->update(['usos_acumulados' => 40]);
        $falla = Incidencia::create([
            'user_id' => $this->alumno->id, 'centro_computo_id' => $this->laboratorio->id, 'numero_maquina' => 2,
            'categoria' => 'Mouse', 'estado' => 'pendiente',
        ]);

        $this->actingAs($this->encargado)->put("/encargado/incidencias/{$falla->id}/resolver", ['nota_resolucion' => 'Se cambió el mouse']);

        $this->assertSame(40, (int) $this->equipo(2)->usos_acumulados);
        $this->assertSame('resuelta', $falla->fresh()->estado);
    }

    public function test_el_mantenimiento_masivo_reinicia_el_contador_de_las_computadoras_elegidas(): void
    {
        $this->equipo(1)->update(['usos_acumulados' => 30]);
        $this->equipo(2)->update(['usos_acumulados' => 25]);
        $this->equipo(3)->update(['usos_acumulados' => 20]);

        $this->actingAs($this->usuario('Administrador'))->post('/admin/equipos/mantenimiento-masivo', [
            'equipos' => [$this->equipo(1)->id, $this->equipo(2)->id],
        ])->assertSessionHas('success');

        $this->assertSame(0, (int) $this->equipo(1)->usos_acumulados);
        $this->assertSame(0, (int) $this->equipo(2)->usos_acumulados);
        $this->assertSame(20, (int) $this->equipo(3)->usos_acumulados);
    }

    public function test_el_comando_completar_crea_las_computadoras_que_falten_con_sus_usos_reales(): void
    {
        // Un laboratorio que se quedó sin su registro de computadoras
        Equipo::where('centro_computo_id', $this->laboratorio->id)->delete();
        $this->enUsoLibre($this->alumno, $this->laboratorio, 4, ['fecha_hora_salida' => now()]);
        $this->enUsoLibre($this->usuario('Alumno'), $this->laboratorio, 4, ['fecha_hora_salida' => now()]);

        // Sin --aplicar sólo informa
        $this->artisan('equipos:completar')->assertSuccessful();
        $this->assertSame(0, Equipo::where('centro_computo_id', $this->laboratorio->id)->count());

        $this->artisan('equipos:completar', ['--aplicar' => true])->assertSuccessful();

        $this->assertSame(10, Equipo::where('centro_computo_id', $this->laboratorio->id)->count());
        $this->assertSame(2, (int) $this->equipo(4)->usos_historicos);
        $this->assertSame(0, (int) $this->equipo(5)->usos_historicos);
    }
}
