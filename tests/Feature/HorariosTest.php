<?php

namespace Tests\Feature;

use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El administrador arma el horario de los laboratorios.
 */
class HorariosTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected $admin;
    protected $laboratorio;
    protected $existente;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->usuario('Administrador');
        $this->laboratorio = $this->laboratorio();
        $this->existente = $this->clase($this->laboratorio);    // miércoles de 10:00 a 12:00
    }

    /** Los datos del formulario para una clase nueva, con otro grupo y otro profesor. */
    protected function formulario(array $cambios = []): array
    {
        $semestre = $this->semestre();

        return array_merge([
            'centro_computo_id' => $this->laboratorio->id,
            'materia_id' => Materia::create(['nombre_materia' => 'Programación Web'])->id,
            'user_id' => $this->usuario('Profesor')->id,
            'grupo_id' => Grupo::create(['nombre_grupo' => '7SM-PROGRAMACIÓN WEB', 'semestre_id' => $semestre->id])->id,
            'tipo_reserva' => 'recurrente',
            'dia_semana' => 'Jueves',
            'hora_inicio' => '08:00',
            'hora_fin' => '10:00',
        ], $cambios);
    }

    protected function crear(array $cambios = [])
    {
        return $this->actingAs($this->admin)->post('/admin/horarios', $this->formulario($cambios));
    }

    public function test_crea_una_clase_en_un_hueco_libre(): void
    {
        $this->crear()->assertSessionHas('success');

        $this->assertDatabaseHas('horarios', ['dia_semana' => 'Jueves', 'centro_computo_id' => $this->laboratorio->id, 'tipo_reserva' => 'recurrente']);
    }

    public function test_no_deja_dos_clases_a_la_misma_hora_en_el_mismo_laboratorio(): void
    {
        $this->crear(['dia_semana' => 'Miércoles', 'hora_inicio' => '11:00', 'hora_fin' => '13:00'])
            ->assertSessionHasErrors('choque');

        $this->assertSame(1, Horario::count());
    }

    public function test_una_clase_puede_empezar_justo_cuando_termina_la_anterior(): void
    {
        $this->crear(['dia_semana' => 'Miércoles', 'hora_inicio' => '12:00', 'hora_fin' => '14:00'])
            ->assertSessionHas('success');

        $this->assertSame(2, Horario::count());
    }

    public function test_un_profesor_no_puede_tener_dos_clases_a_la_misma_hora_en_laboratorios_distintos(): void
    {
        $otro = $this->laboratorio('Laboratorio B');

        $this->crear([
            'centro_computo_id' => $otro->id, 'user_id' => $this->existente->user_id,
            'dia_semana' => 'Miércoles', 'hora_inicio' => '10:00', 'hora_fin' => '12:00',
        ])->assertSessionHasErrors('choque');

        $this->assertSame(1, Horario::count());
    }

    public function test_la_hora_de_fin_debe_ser_posterior_a_la_de_inicio(): void
    {
        $this->crear(['hora_inicio' => '10:00', 'hora_fin' => '09:00'])->assertSessionHasErrors('hora_fin');
    }

    public function test_solo_un_profesor_puede_impartir_la_clase(): void
    {
        $this->crear(['user_id' => $this->usuario('Alumno')->id])->assertSessionHasErrors('user_id');
    }

    public function test_una_reserva_especial_debe_caer_dentro_del_semestre(): void
    {
        $this->crear(['tipo_reserva' => 'especial', 'fecha_especial' => '2026-08-20', 'dia_semana' => null])
            ->assertSessionHasErrors('fecha_especial');

        $this->crear(['tipo_reserva' => 'especial', 'fecha_especial' => '2026-03-20', 'dia_semana' => null])
            ->assertSessionHas('success');

        // El día de la semana lo deduce de la fecha
        $this->assertDatabaseHas('horarios', ['tipo_reserva' => 'especial', 'dia_semana' => 'Viernes']);
    }

    public function test_borrar_una_clase_la_manda_a_la_papelera_y_se_puede_recuperar(): void
    {
        $this->actingAs($this->admin)->delete("/admin/horarios/{$this->existente->id}");

        $this->assertSame(0, Horario::count());
        $this->assertSame(1, Horario::onlyTrashed()->count());

        $this->actingAs($this->admin)->post("/admin/horarios/{$this->existente->id}/restaurar");

        $this->assertSame(1, Horario::count());
    }

    public function test_el_pdf_de_horarios_saca_cada_laboratorio_en_una_sola_hoja(): void
    {
        $otro = $this->laboratorio('Laboratorio B');
        $this->clase($otro, ['dia_semana' => 'Lunes', 'hora_inicio' => '07:00', 'hora_fin' => '09:00']);

        $pdf = $this->actingAs($this->admin)->post('/admin/horarios/exportar-pdf', [
            'centros_ids' => [$this->laboratorio->id, $otro->id],
            'fecha_actual' => now()->toDateString(),
        ])->assertOk()->getContent();

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(2, preg_match_all('~/Type\s*/Page(?!s)~', $pdf), 'Dos laboratorios, dos hojas');
    }
}
