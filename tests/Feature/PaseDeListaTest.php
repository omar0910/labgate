<?php

namespace Tests\Feature;

use App\Mail\AvisoAsistenciaClase;
use App\Models\Asistencia;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Escenario;
use Tests\TestCase;

/**
 * El profesor pasa lista de su clase.
 */
class PaseDeListaTest extends TestCase
{
    use RefreshDatabase, Escenario;

    protected $clase;
    protected $profesor;
    protected $ana;
    protected $beto;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->clase = $this->clase($this->laboratorio());
        $this->profesor = User::find($this->clase->user_id);
        $this->ana = $this->alumnoDe($this->clase);
        $this->beto = $this->alumnoDe($this->clase);
    }

    protected function pasarLista(array $asistencias, ?string $fecha = null, ?User $quien = null)
    {
        return $this->actingAs($quien ?? $this->profesor)->post('/profesor/guardar-asistencia', [
            'horario_id' => $this->clase->id,
            'fecha' => $fecha ?? now()->toDateString(),
            'asistencias' => $asistencias,
        ]);
    }

    public function test_guarda_presentes_faltas_y_justificadas_con_su_comentario(): void
    {
        $this->pasarLista([
            $this->ana->id => ['estado' => 'presente'],
            $this->beto->id => ['estado' => 'justificado', 'comentario' => 'Cita médica'],
        ])->assertSessionHas('success');

        $this->assertDatabaseHas('asistencias', ['user_id' => $this->ana->id, 'estado' => 'presente', 'horario_id' => $this->clase->id]);
        $this->assertDatabaseHas('asistencias', ['user_id' => $this->beto->id, 'estado' => 'justificado', 'comentario' => 'Cita médica']);
    }

    public function test_corregir_la_lista_no_duplica_registros(): void
    {
        $this->pasarLista([$this->ana->id => ['estado' => 'falta']]);
        $this->pasarLista([$this->ana->id => ['estado' => 'presente']]);

        $this->assertSame(1, Asistencia::where('user_id', $this->ana->id)->count());
        $this->assertSame('presente', Asistencia::where('user_id', $this->ana->id)->value('estado'));
    }

    public function test_respeta_la_computadora_del_alumno_que_ya_se_habia_registrado(): void
    {
        $this->registradoEnClase($this->ana, $this->clase, 6);

        $this->pasarLista([$this->ana->id => ['estado' => 'presente'], $this->beto->id => ['estado' => 'falta']]);

        $this->assertDatabaseHas('asistencias', ['user_id' => $this->ana->id, 'estado' => 'presente', 'numero_maquina' => 6]);
        $this->assertSame(1, Asistencia::where('user_id', $this->ana->id)->count());
    }

    public function test_ignora_a_quien_no_es_del_grupo(): void
    {
        $ajeno = $this->usuario('Alumno');

        $this->pasarLista([$this->ana->id => ['estado' => 'presente'], $ajeno->id => ['estado' => 'presente']]);

        $this->assertSame(0, Asistencia::where('user_id', $ajeno->id)->count());
    }

    public function test_un_profesor_no_puede_pasar_lista_de_la_clase_de_otro(): void
    {
        $this->pasarLista([$this->ana->id => ['estado' => 'presente']], null, $this->usuario('Profesor'))
            ->assertNotFound();

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_no_se_puede_pasar_lista_de_una_fecha_que_aun_no_llega(): void
    {
        $this->pasarLista([$this->ana->id => ['estado' => 'presente']], '2026-03-18')
            ->assertSessionHasErrors('fecha');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_ni_de_un_dia_en_que_esa_clase_no_se_imparte(): void
    {
        // El martes anterior: la clase es de los miércoles
        $this->pasarLista([$this->ana->id => ['estado' => 'presente']], '2026-03-10')
            ->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_si_no_marca_a_nadie_no_guarda_nada_y_se_lo_dice(): void
    {
        $this->pasarLista([$this->ana->id => [], $this->beto->id => []])->assertSessionHas('error');

        $this->assertDatabaseCount('asistencias', 0);
    }

    public function test_avisa_por_correo_a_cada_alumno_de_lo_que_se_le_anoto(): void
    {
        $this->pasarLista([$this->ana->id => ['estado' => 'presente'], $this->beto->id => ['estado' => 'falta']]);

        Mail::assertQueued(AvisoAsistenciaClase::class, 2);
    }
}
