<?php

namespace App\Demo;

use App\Models\CentroComputo;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Arma los datos de la demostración: un semestre en curso alrededor de la fecha
 * de hoy, con sus laboratorios, personas, clases y el historial de lo ocurrido
 * hasta ayer. Lo de hoy lo pone el Simulador.
 *
 * Todo es ficticio y sale siempre igual (misma semilla), salvo las fechas, que
 * se calculan respecto al día en que se ejecuta para que la demostración nunca
 * se vea vieja.
 */
class Generador
{
    const DIAS = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

    /** Semanas del semestre antes y después de hoy. */
    const SEMANAS_ANTES = 9;
    const SEMANAS_DESPUES = 9;

    protected Carbon $hoy;
    protected Carbon $inicio;
    protected Carbon $fin;
    protected int $semestreId;
    protected string $contrasena;

    protected array $laboratorios = [];     // nombre => [id, capacidad, uso libre]
    protected array $profesores = [];       // posición => id
    protected array $encargados = [];       // ids de quienes resuelven fallas
    protected array $generaciones = [];     // clave => ids de sus alumnos
    protected array $horarios = [];         // filas de horarios, con su día (0 a 4) y su grupo
    protected array $miembros = [];         // grupo_id => ids de alumnos
    protected array $inhabiles = [];        // fechas sin clases
    protected array $ocupado = ['lab' => [], 'prof' => [], 'gen' => []];
    protected array $carga = [];

    public function generar(): array
    {
        mt_srand(20260101);

        $this->hoy = Carbon::today();
        $this->inicio = $this->hoy->copy()->subWeeks(self::SEMANAS_ANTES)->startOfWeek(Carbon::MONDAY);
        $this->fin = $this->hoy->copy()->addWeeks(self::SEMANAS_DESPUES)->startOfWeek(Carbon::MONDAY)->addDays(4);
        $this->contrasena = Hash::make(config('demo.contrasena'));

        $this->crearPersonal();
        $this->crearLaboratorios();
        $this->crearSemestre();
        $this->crearAlumnos();
        $this->crearClases();
        $asistencias = $this->crearHistorialDeClases();
        $usoLibre = $this->crearHistorialDeUsoLibre();
        $fallas = $this->crearFallasYMantenimientos();
        $this->cuadrarContadoresDeEquipos();

        return [
            'Laboratorios' => count($this->laboratorios),
            'Profesores' => count($this->profesores),
            'Alumnos' => array_sum(array_map('count', $this->generaciones)),
            'Clases por semana' => count($this->horarios),
            'Registros de asistencia' => $asistencias,
            'Sesiones de uso libre' => $usoLibre,
            'Reportes de fallas' => $fallas,
        ];
    }

    // ------------------------------------------------------------------ personas

    protected function crearPersonal(): void
    {
        $cuentas = config('demo.cuentas');

        // Las cuatro cuentas con las que se entra a la demostración van primero.
        $this->persona('Administrador', 'Daniela', 'Rojas', 'Medina', $cuentas['Administrador']);
        $this->encargados[] = $this->persona('Encargado', 'Luis Alberto', 'Fernández', 'Soto', $cuentas['Encargado']);
        $this->profesores[0] = $this->persona('Profesor', 'Mariana', 'Ortega', 'Ruiz', $cuentas['Profesor'], ['academia' => Catalogo::ACADEMIAS[0]]);

        $this->encargados[] = $this->persona('Encargado', 'Rebeca', 'Núñez', 'Lara', 'rebeca.nunez');

        for ($i = 1; $i <= 11; $i++) {
            [$nombre, $paterno, $materno] = $this->nombreAlAzar();
            $usuario = Str::lower(Str::ascii(Str::substr($nombre, 0, 1) . $paterno)) . $i;

            $this->profesores[$i] = $this->persona('Profesor', $nombre, $paterno, $materno, $usuario, [
                'academia' => Catalogo::ACADEMIAS[$i % count(Catalogo::ACADEMIAS)],
            ]);
        }
    }

    protected function persona(string $rol, string $nombre, string $paterno, string $materno, string $usuario, array $extra = []): int
    {
        return User::create(array_merge([
            'name' => $nombre,
            'apellido_paterno' => $paterno,
            'apellido_materno' => $materno,
            'username' => $usuario,
            'email' => $usuario . '@' . config('marca.dominio_correo'),
            'password' => $this->contrasena,
            'rol' => $rol,
            'activo' => true,
        ], $extra))->id;
    }

    protected function nombreAlAzar(): array
    {
        return [
            Catalogo::NOMBRES[mt_rand(0, count(Catalogo::NOMBRES) - 1)],
            Catalogo::APELLIDOS[mt_rand(0, count(Catalogo::APELLIDOS) - 1)],
            Catalogo::APELLIDOS[mt_rand(0, count(Catalogo::APELLIDOS) - 1)],
        ];
    }

    protected function crearAlumnos(): void
    {
        $consecutivo = 0;
        $ahora = now()->toDateTimeString();

        foreach (Catalogo::GENERACIONES as $clave => $cuantos) {
            // "5SM": quinto semestre, así que entró hace dos años
            $ingreso = $this->hoy->year - intdiv((int) $clave[0] - 1, 2);
            $carrera = Catalogo::CARRERAS[$clave[1]];
            $filas = [];

            for ($i = 0; $i < $cuantos; $i++) {
                $consecutivo++;
                $matricula = substr((string) $ingreso, 2) . '310' . str_pad((string) $consecutivo, 4, '0', STR_PAD_LEFT);
                $esElDeDemostracion = $clave === Catalogo::GENERACION_DEL_ALUMNO_DEMO && $i === 0;

                [$nombre, $paterno, $materno] = $esElDeDemostracion ? ['Emilio', 'Sandoval', 'Peña'] : $this->nombreAlAzar();

                $filas[] = [
                    'name' => $nombre,
                    'apellido_paterno' => $paterno,
                    'apellido_materno' => $materno,
                    'username' => $esElDeDemostracion ? config('demo.cuentas.Alumno') : null,
                    'matricula' => $matricula,
                    'email' => 'L' . $matricula . '@' . config('marca.dominio_correo'),
                    'password' => $this->contrasena,
                    'rol' => 'Alumno',
                    'carrera' => $carrera,
                    'activo' => 1,
                    'created_at' => $ahora,
                    'updated_at' => $ahora,
                ];
            }

            DB::table('users')->insert($filas);

            $this->generaciones[$clave] = DB::table('users')
                ->whereIn('matricula', array_column($filas, 'matricula'))
                ->orderBy('id')->pluck('id')->all();
        }
    }

    // --------------------------------------------------------- laboratorios y semestre

    protected function crearLaboratorios(): void
    {
        foreach (Catalogo::LABORATORIOS as [$nombre, $capacidad, $usoLibre]) {
            // Al crearlo, el modelo le da de alta sus computadoras (de la 1 a su capacidad)
            $centro = CentroComputo::create([
                'nombre_centro' => $nombre,
                'capacidad' => $capacidad,
                'permite_uso_libre' => $usoLibre,
            ]);

            $this->laboratorios[$nombre] = ['id' => $centro->id, 'capacidad' => $capacidad, 'uso_libre' => $usoLibre];
        }
    }

    protected function crearSemestre(): void
    {
        $mes = fn(Carbon $fecha) => Str::ucfirst($fecha->locale('es')->translatedFormat('F Y'));

        $this->semestreId = DB::table('semestres')->insertGetId([
            'nombre' => $mes($this->inicio) . ' - ' . $mes($this->fin),
            'fecha_inicio' => $this->inicio->toDateString(),
            'fecha_fin' => $this->fin->toDateString(),
            'es_activo' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Un día sin clases ya pasado y otro por venir
        $pasado = $this->hoy->copy()->subWeeks(3)->startOfWeek(Carbon::MONDAY)->addDays(4);
        $futuro = $this->hoy->copy()->addWeeks(2)->startOfWeek(Carbon::MONDAY);

        foreach ([[$pasado, 'Suspensión de labores docentes'], [$futuro, 'Día festivo oficial']] as [$fecha, $motivo]) {
            DB::table('dias_inhabiles')->insert([
                'fecha' => $fecha->toDateString(), 'motivo' => $motivo, 'semestre_id' => $this->semestreId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->inhabiles[] = $fecha->toDateString();
        }
    }

    // -------------------------------------------------------------------- clases

    protected function crearClases(): void
    {
        $materias = [];
        $inscripcion = $this->inicio->copy()->subDays(3)->setTime(10, 0)->toDateTimeString();

        foreach (Catalogo::CLASES as $numero => [$generacion, $materia, $laboratorio, $profesor]) {
            if (! isset($materias[$materia])) {
                $siglas = Str::upper(Str::substr(Str::ascii(preg_replace('/[^\p{L}]/u', '', $materia)), 0, 3));
                $materias[$materia] = DB::table('materias')->insertGetId([
                    'clave' => $siglas . '-' . (1001 + count($materias) * 7),
                    'nombre_materia' => $materia,
                    'creditos' => (string) (4 + $numero % 2),
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            $grupoId = DB::table('grupos')->insertGetId([
                'nombre_grupo' => $generacion . '-' . Str::upper($materia),
                'semestre_id' => $this->semestreId,
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $this->miembros[$grupoId] = $this->generaciones[$generacion];
            DB::table('alumno_grupo')->insert(array_map(fn($alumno) => [
                'user_id' => $alumno, 'grupo_id' => $grupoId, 'created_at' => $inscripcion, 'updated_at' => $inscripcion,
            ], $this->generaciones[$generacion]));

            // Dos veces por semana, dos horas, en días distintos
            $diasUsados = [];
            for ($vez = 0; $vez < 2; $vez++) {
                $lugar = $this->buscarLugar($numero, $generacion, $laboratorio, $profesor, $diasUsados);
                if (! $lugar) {
                    continue;
                }

                [$dia, $hora] = $lugar;
                $diasUsados[] = $dia;
                foreach ([$hora, $hora + 1] as $h) {
                    $this->ocupado['lab'][$laboratorio][$dia][$h] = true;
                    $this->ocupado['prof'][$profesor][$dia][$h] = true;
                    $this->ocupado['gen'][$generacion][$dia][$h] = true;
                    $this->carga[$dia][$h] = ($this->carga[$dia][$h] ?? 0) + 1;
                }

                $fila = [
                    'tipo_reserva' => 'recurrente',
                    'user_id' => $this->profesores[$profesor],
                    'materia_id' => $materias[$materia],
                    'grupo_id' => $grupoId,
                    'centro_computo_id' => $this->laboratorios[$laboratorio]['id'],
                    'dia_semana' => self::DIAS[$dia],
                    'hora_inicio' => sprintf('%02d:00:00', $hora),
                    'hora_fin' => sprintf('%02d:00:00', $hora + 2),
                    'semestre_id' => $this->semestreId,
                    'created_at' => $inscripcion, 'updated_at' => $inscripcion,
                ];

                $this->horarios[] = (object) array_merge($fila, [
                    'id' => DB::table('horarios')->insertGetId($fila),
                    'dia' => $dia,
                    'capacidad' => $this->laboratorios[$laboratorio]['capacidad'],
                ]);
            }
        }
    }

    /**
     * El día y la hora para una clase: libres para su laboratorio, su profesor y
     * su generación, y donde menos clases haya ya, para que la semana quede
     * pareja y a casi cualquier hora haya algo en curso que ver.
     */
    protected function buscarLugar(int $numero, string $generacion, string $laboratorio, int $profesor, array $diasUsados): ?array
    {
        // Los grupos de la mañana, de 7 a 14; los de la tarde, de 14 a 21
        $horas = substr($generacion, -1) === 'M' ? range(7, 12) : range(14, 19);
        $mejor = null;
        $menor = INF;

        foreach (array_keys(self::DIAS) as $dia) {
            if (in_array($dia, $diasUsados, true)) {
                continue;
            }

            foreach ($horas as $hora) {
                foreach ([$hora, $hora + 1] as $h) {
                    if (isset($this->ocupado['lab'][$laboratorio][$dia][$h])
                        || isset($this->ocupado['prof'][$profesor][$dia][$h])
                        || isset($this->ocupado['gen'][$generacion][$dia][$h])) {
                        continue 2;
                    }
                }

                $costo = ($this->carga[$dia][$hora] ?? 0) + ($this->carga[$dia][$hora + 1] ?? 0);
                // Mejor con un día de por medio (lunes y miércoles) que seguidos
                foreach ($diasUsados as $usado) {
                    $costo += abs($usado - $dia) === 1 ? 0.5 : 0;
                }
                // Repartidas en la semana: que ni el profesor ni el grupo junten todas
                // sus clases en dos días (quien entre a la demostración con sus
                // cuentas debe encontrar clases casi cualquier día)
                // Con las dos cuentas de demostración pesa mucho más: deben tener
                // clase de lunes a viernes.
                $deDemostracion = $generacion === Catalogo::GENERACION_DEL_ALUMNO_DEMO;
                $costo += count($this->ocupado['prof'][$profesor][$dia] ?? []) * ($profesor === 0 ? 3 : 0.6);
                $costo += count($this->ocupado['gen'][$generacion][$dia] ?? []) * ($deDemostracion ? 2 : 0.45);
                // Para que los empates no caigan siempre en lunes a las 7
                $costo += (($numero * 7 + $dia * 3 + $hora) % 5) / 100;

                if ($costo < $menor) {
                    $menor = $costo;
                    $mejor = [$dia, $hora];
                }
            }
        }

        return $mejor;
    }

    // ----------------------------------------------------------------- historial

    /** Los días con clases desde que empezó el semestre hasta ayer. */
    protected function diasPasados(): array
    {
        $dias = [];

        for ($fecha = $this->inicio->copy(); $fecha->lt($this->hoy); $fecha->addDay()) {
            if ($fecha->dayOfWeekIso <= 5 && ! in_array($fecha->toDateString(), $this->inhabiles, true)) {
                $dias[] = $fecha->copy();
            }
        }

        return $dias;
    }

    protected function crearHistorialDeClases(): int
    {
        $total = 0;
        $pendientes = [];
        $delProfesor = [];
        $profesorDemo = $this->profesores[0];

        foreach ($this->diasPasados() as $fecha) {
            $reciente = $fecha->diffInDays($this->hoy) <= 10;

            foreach ($this->horarios as $horario) {
                if ($horario->dia !== $fecha->dayOfWeekIso - 1) {
                    continue;
                }

                // ¿Qué registró el profesor? Casi siempre que dio su clase. Las de
                // los últimos días a veces siguen sin registrar: son las "clases
                // pendientes" que el sistema le recuerda.
                $azar = mt_rand(0, 999);
                $porRegistrar = $reciente && $azar < ($horario->user_id === $profesorDemo ? 220 : 70);
                $estado = $porRegistrar ? null : ($azar >= 965 ? 'falta' : ($azar >= 935 ? 'justificado' : 'asistio'));

                if ($estado) {
                    $cierre = $fecha->toDateString() . ' ' . $horario->hora_fin;
                    $delProfesor[] = [
                        'horario_id' => $horario->id, 'fecha' => $fecha->toDateString(), 'estado' => $estado,
                        'observaciones' => $estado === 'justificado' ? Catalogo::MOTIVOS_DEL_PROFESOR[mt_rand(0, 2)] : null,
                        'created_at' => $cierre, 'updated_at' => $cierre,
                    ];
                }

                // Si el profesor no dio la clase, nadie se registra en ella
                if (in_array($estado, ['falta', 'justificado'], true)) {
                    continue;
                }

                foreach (Sesion::filas($horario, $fecha, $this->miembros[$horario->grupo_id], $horario->capacidad, $estado) as $fila) {
                    $pendientes[] = $fila + ['created_at' => $fila['fecha_hora_registro'], 'updated_at' => $fila['fecha_hora_registro']];
                }
            }

            if (count($pendientes) >= 1000) {
                $total += $this->guardar('asistencias', $pendientes);
            }
        }

        $this->guardar('asistencia_profesores', $delProfesor);

        return $total + $this->guardar('asistencias', $pendientes);
    }

    protected function crearHistorialDeUsoLibre(): int
    {
        $filas = [];
        $todos = array_merge(...array_values($this->generaciones));

        foreach ($this->diasPasados() as $fecha) {
            foreach ($this->laboratorios as $nombre => $laboratorio) {
                if ($laboratorio['uso_libre']) {
                    array_push($filas, ...$this->usoLibreDelDia($fecha, $nombre, $laboratorio, $todos, 21));
                }
            }
        }

        // Lo de hoy, hasta la hora en que se siembra (lo que sigue lo va poniendo el simulador)
        if ($this->hoy->dayOfWeekIso <= 5 && ! in_array($this->hoy->toDateString(), $this->inhabiles, true)) {
            foreach ($this->laboratorios as $nombre => $laboratorio) {
                if ($laboratorio['uso_libre']) {
                    array_push($filas, ...$this->usoLibreDelDia($this->hoy, $nombre, $laboratorio, $todos, (int) now()->format('G') - 1));
                }
            }
        }

        return $this->guardar('asistencias', $filas);
    }

    /** Las sesiones de uso libre de un laboratorio en un día, en las horas en que no tiene clase. */
    protected function usoLibreDelDia(Carbon $fecha, string $nombre, array $laboratorio, array $alumnos, int $hastaLaHora): array
    {
        $dia = $fecha->dayOfWeekIso - 1;
        $libres = array_values(array_filter(range(7, 20), fn($h) => $h < $hastaLaHora && ! isset($this->ocupado['lab'][$nombre][$dia][$h])));

        if (empty($libres)) {
            return [];
        }

        $filas = [];
        $enUso = [];    // pc => hasta qué minuto del día está ocupada

        for ($i = mt_rand(6, 14); $i > 0; $i--) {
            $hora = $libres[mt_rand(0, count($libres) - 1)];
            $entra = $hora * 60 + mt_rand(0, 50);
            // Sale antes de que empiece la siguiente clase del laboratorio
            $tope = $hora + 1;
            while ($tope < 21 && ! isset($this->ocupado['lab'][$nombre][$dia][$tope])) {
                $tope++;
            }
            $sale = min($entra + mt_rand(25, 100), $tope * 60 - 2, $hastaLaHora * 60 + 55);
            if ($sale - $entra < 10) {
                continue;
            }

            $pc = mt_rand(1, $laboratorio['capacidad']);
            if (($enUso[$pc] ?? 0) > $entra) {
                continue;
            }
            $enUso[$pc] = $sale;

            $registro = $fecha->copy()->startOfDay()->addMinutes($entra)->toDateTimeString();
            $filas[] = [
                'horario_id' => null, 'user_id' => $alumnos[mt_rand(0, count($alumnos) - 1)], 'fecha' => $fecha->toDateString(),
                'tipo' => 'Uso Libre', 'estado' => 'presente', 'centro_computo_id' => $laboratorio['id'], 'numero_maquina' => $pc,
                'equipo_personal' => 0, 'comentario' => null, 'fecha_hora_registro' => $registro,
                'fecha_hora_salida' => $fecha->copy()->startOfDay()->addMinutes($sale)->toDateTimeString(),
                'created_at' => $registro, 'updated_at' => $registro,
            ];
        }

        return $filas;
    }

    // --------------------------------------------------------------------- fallas

    protected function crearFallasYMantenimientos(): int
    {
        $dias = $this->diasPasados();
        $todos = array_merge(...array_values($this->generaciones));
        $nombres = array_keys($this->laboratorios);
        $categorias = array_keys(Catalogo::FALLAS);
        $filas = [];

        if (empty($dias)) {
            return 0;
        }

        // Lo que reportan los alumnos: casi todo ya atendido, lo de esta semana aún pendiente
        for ($i = 0; $i < 24; $i++) {
            $pendiente = $i >= 20;
            $fecha = $pendiente ? $dias[count($dias) - 1 - mt_rand(0, min(3, count($dias) - 1))] : $dias[mt_rand(0, max(0, count($dias) - 5))];
            $laboratorio = $this->laboratorios[$nombres[mt_rand(0, count($nombres) - 1)]];
            $categoria = $categorias[mt_rand(0, count($categorias) - 1)];
            $creada = $fecha->copy()->setTime(mt_rand(8, 19), mt_rand(0, 59));
            $resuelta = $creada->copy()->addHours(mt_rand(2, 60));

            $filas[] = [
                'user_id' => $todos[mt_rand(0, count($todos) - 1)],
                'centro_computo_id' => $laboratorio['id'],
                'numero_maquina' => mt_rand(1, $laboratorio['capacidad'] - 2),
                'categoria' => $categoria,
                'descripcion' => Catalogo::FALLAS[$categoria][mt_rand(0, count(Catalogo::FALLAS[$categoria]) - 1)],
                'estado' => $pendiente ? 'pendiente' : 'resuelta',
                'nota_resolucion' => $pendiente ? null : Catalogo::SOLUCIONES[mt_rand(0, count(Catalogo::SOLUCIONES) - 1)],
                'resuelta_por' => $pendiente ? null : $this->encargados[mt_rand(0, count($this->encargados) - 1)],
                'fecha_resolucion' => $pendiente ? null : $resuelta->toDateTimeString(),
                'created_at' => $creada->toDateTimeString(),
                'updated_at' => ($pendiente ? $creada : $resuelta)->toDateTimeString(),
            ];
        }

        // Mantenimientos que mandó el encargado desde el Monitor: cinco ya hechos
        // (reinician el contador de usos de esa PC) y dos en curso, con la PC bloqueada.
        $bloqueo = 'Bloqueo manual desde el Monitor Operativo para revisión y limpieza de este equipo.';
        $hechos = [['CAD 1', 3], ['CAD 1', 11], ['CEC', 7], ['CAD 2', 14], ['CCNA', 5]];
        $enCurso = [['CAD 1', 25], ['CEC', 30]];

        foreach ($hechos as $i => [$nombre, $pc]) {
            $creada = $dias[(int) (count($dias) * (0.25 + $i * 0.12))]->copy()->setTime(9 + $i, 20);
            $resuelta = $creada->copy()->addDay()->setTime(13, 5);
            $filas[] = [
                'user_id' => $this->encargados[$i % count($this->encargados)], 'centro_computo_id' => $this->laboratorios[$nombre]['id'],
                'numero_maquina' => $pc, 'categoria' => 'Mantenimiento Preventivo (Manual)', 'descripcion' => $bloqueo, 'estado' => 'resuelta',
                'nota_resolucion' => 'Limpieza interna, revisión de periféricos y actualización del sistema.',
                'resuelta_por' => $this->encargados[0], 'fecha_resolucion' => $resuelta->toDateTimeString(),
                'created_at' => $creada->toDateTimeString(), 'updated_at' => $resuelta->toDateTimeString(),
            ];

            DB::table('equipos')->where('centro_computo_id', $this->laboratorios[$nombre]['id'])->where('numero_maquina', $pc)
                ->update(['ultimo_mantenimiento' => $resuelta->toDateTimeString()]);
        }

        foreach ($enCurso as [$nombre, $pc]) {
            $creada = end($dias)->copy()->setTime(12, 40);
            $filas[] = [
                'user_id' => $this->encargados[0], 'centro_computo_id' => $this->laboratorios[$nombre]['id'], 'numero_maquina' => $pc,
                'categoria' => 'Mantenimiento Preventivo (Manual)', 'descripcion' => $bloqueo, 'estado' => 'pendiente',
                'nota_resolucion' => null, 'resuelta_por' => null, 'fecha_resolucion' => null,
                'created_at' => $creada->toDateTimeString(), 'updated_at' => $creada->toDateTimeString(),
            ];

            DB::table('equipos')->where('centro_computo_id', $this->laboratorios[$nombre]['id'])->where('numero_maquina', $pc)
                ->update(['estado' => 'mantenimiento']);
        }

        return $this->guardar('incidencias', $filas);
    }

    /**
     * Los registros se guardaron directo en la tabla (son miles), así que los
     * contadores de uso de cada computadora se calculan aquí de una vez: el
     * histórico con todos sus registros y el odómetro con los posteriores a su
     * último mantenimiento.
     */
    protected function cuadrarContadoresDeEquipos(): void
    {
        DB::statement('
            UPDATE equipos e SET
                usos_historicos = (SELECT COUNT(*) FROM asistencias a
                    WHERE a.centro_computo_id = e.centro_computo_id AND a.numero_maquina = e.numero_maquina),
                usos_acumulados = (SELECT COUNT(*) FROM asistencias a
                    WHERE a.centro_computo_id = e.centro_computo_id AND a.numero_maquina = e.numero_maquina
                      AND (e.ultimo_mantenimiento IS NULL OR a.created_at > e.ultimo_mantenimiento))
        ');
    }

    /** Guarda por tandas y vacía la lista. Devuelve cuántas filas guardó. */
    protected function guardar(string $tabla, array &$filas): int
    {
        $cuantas = count($filas);

        foreach (array_chunk($filas, 500) as $tanda) {
            DB::table($tabla)->insert($tanda);
        }

        $filas = [];

        return $cuantas;
    }
}
