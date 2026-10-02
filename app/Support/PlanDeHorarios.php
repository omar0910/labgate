<?php

namespace App\Support;

use App\Models\CentroComputo;
use App\Models\EquivalenciaImportacion;
use App\Models\Grupo;
use App\Models\Horario;
use App\Models\Materia;
use App\Models\User;

/**
 * Compara lo que dice el archivo de horarios con lo que ya tiene el sistema.
 *
 * No guarda nada: arma el plan de lo que pasaría (qué clases son nuevas, cuáles
 * cambian de hora, cuáles se quedan igual y cuáles del sistema ya no aparecen en
 * el archivo) y señala lo que no pudo resolver solo, para preguntarlo antes.
 *
 * Una clase se reconoce por LABORATORIO + GRUPO + MATERIA + DÍA, nunca por la
 * hora. Así, cuando en el archivo nuevo esa clase cambió de horario, se edita la
 * que ya existe en lugar de borrarla y crear otra: conserva su id y con él las
 * asistencias ya registradas.
 */
class PlanDeHorarios
{
    /** Nombres con los que suele venir cada laboratorio, para proponerlos. */
    const ALIAS_AREAS = [
        'CI'    => 'CAD 2',
        'CADII' => 'CAD 2',
        'CAD2'  => 'CAD 2',
        'CA'    => 'CAD 1',
        'CAD'   => 'CAD 1',
        'CAD1'  => 'CAD 1',
        'CE'    => 'CEC',
        'CEC'   => 'CEC',
        'CN'    => 'CCNA',
        'CCNA'  => 'CCNA',
    ];

    protected $semestreId;

    /** Decisiones que ya tomó la persona en la vista previa. */
    protected $decisiones;

    public function __construct(int $semestreId, array $decisiones = [])
    {
        $this->semestreId = $semestreId;
        $this->decisiones = $decisiones;
    }

    /**
     * Arma el plan a partir de las clases que trae el archivo.
     */
    public function analizar(array $clases): array
    {
        $centros  = CentroComputo::pluck('nombre_centro', 'id')->all();
        $materias = Materia::pluck('nombre_materia', 'id')->all();
        // Sólo cuentas activas: a un docente dado de baja no se le asignan clases nuevas
        $docentes = User::whereIn('rol', ['Profesor', 'Administrador', 'Encargado'])->activos()->get();

        $areasPendientes    = [];
        $materiasPendientes = [];
        $docentesPendientes = [];

        $resueltas = [];

        foreach ($clases as $clase) {
            $centroId  = $this->resolverArea($clase['area'], $centros, $areasPendientes);
            $materiaId = $this->resolverMateria($clase['materia'], $materias, $materiasPendientes);
            $docenteId = $this->resolverDocente($clase['docente'], $docentes, $docentesPendientes);

            $resueltas[] = $clase + [
                'centro_id'  => $centroId,
                'materia_id' => $materiaId,
                'docente_id' => $docenteId,
            ];
        }

        // Los grupos se resuelven al final: hace falta saber la materia del sistema
        // para armar el nombre "1EV-Introducción a la Programación".
        $gruposNuevos = [];
        foreach ($resueltas as $i => $clase) {
            $resueltas[$i]['grupo_id'] = $this->resolverGrupo($clase, $materias, $gruposNuevos);
        }

        $centrosDelArchivo = array_values(array_unique(array_filter(array_column($resueltas, 'centro_id'))));

        $existentes = $this->horariosExistentes($centrosDelArchivo);
        $usados = [];
        $conDatos = [];

        foreach ($resueltas as $clase) {
            $estado = 'pendiente';
            $horarioId = null;

            if ($clase['centro_id'] && $clase['materia_id'] && $clase['docente_id']) {
                $llave = $this->llave($clase['centro_id'], $clase['grupo_id'], $clase['materia_id'], $clase['dia']);

                // Una misma clase puede tener dos bloques el mismo día; se toma
                // el primero que no haya emparejado ya con otro bloque.
                $existente = null;
                foreach ($existentes[$llave] ?? [] as $candidato) {
                    if (! in_array($candidato->id, $usados, true)) {
                        $existente = $candidato;
                        break;
                    }
                }

                if ($existente) {
                    $usados[] = $existente->id;
                    $horarioId = $existente->id;

                    $mismaHora = substr($existente->hora_inicio, 0, 5) === substr($clase['inicio'], 0, 5)
                        && substr($existente->hora_fin, 0, 5) === substr($clase['fin'], 0, 5);
                    $mismoDocente = (int) $existente->user_id === (int) $clase['docente_id'];

                    $estado = ($mismaHora && $mismoDocente) ? 'igual' : 'cambia';
                } else {
                    $estado = 'nueva';
                }
            }

            $conDatos[] = $clase + ['estado' => $estado, 'horario_id' => $horarioId];
        }

        // Lo que el sistema tiene en esos laboratorios y el archivo ya no trae.
        $sobrantes = [];
        foreach ($existentes as $lista) {
            foreach ($lista as $horario) {
                if (! in_array($horario->id, $usados, true)) {
                    $sobrantes[] = [
                        'id'          => $horario->id,
                        'descripcion' => $this->describir($horario),
                    ];
                }
            }
        }

        return [
            'clases'             => $conDatos,
            'areasPendientes'    => $areasPendientes,
            'materiasPendientes' => $materiasPendientes,
            'docentesPendientes' => $docentesPendientes,
            'gruposNuevos'       => array_values(array_unique($gruposNuevos)),
            'sobrantes'          => $sobrantes,
            'empalmes'           => $this->buscarEmpalmes($conDatos),
            'empalmesSistema'    => $this->empalmesConElSistema($conDatos, $centrosDelArchivo, $usados),
            'resumen'            => [
                'nuevas'     => count(array_filter($conDatos, fn($c) => $c['estado'] === 'nueva')),
                'cambian'    => count(array_filter($conDatos, fn($c) => $c['estado'] === 'cambia')),
                'iguales'    => count(array_filter($conDatos, fn($c) => $c['estado'] === 'igual')),
                'pendientes' => count(array_filter($conDatos, fn($c) => $c['estado'] === 'pendiente')),
            ],
        ];
    }

    // ------------------------------------------------------------------
    //  Resolver a qué corresponde cada texto del archivo
    // ------------------------------------------------------------------

    /*
     * Cómo se resuelve cada texto del archivo, en este orden:
     *
     *   1. Lo que la persona eligió en esta misma revisión (0 = "No importar").
     *   2. Lo que se aprendió en importaciones anteriores.
     *   3. Lo que se reconoce sin ninguna duda: se usa y no se pregunta.
     *   4. Una propuesta fiable (el área "CI" es CAD 2, una materia repetida en el
     *      catálogo): se usa, pero se enseña en la revisión para poder cambiarla.
     *   5. Una propuesta dudosa: NO se usa; se enseña sólo como pista.
     *
     * Lo que se usa es exactamente lo que queda elegido en los menús, así que el
     * conteo de la revisión siempre coincide con lo que se guardaría.
     */

    protected function resolverArea(string $area, array $centros, array &$pendientes)
    {
        $clave = Emparejador::normalizar($area);

        if ($decidido = $this->decidido('area', $clave, $area, $pendientes)) {
            return $decidido === -1 ? null : $decidido;
        }

        $aprendidas = $this->aprendidas('area');
        if (isset($aprendidas[$clave])) {
            return (int) $aprendidas[$clave];
        }

        // Por el nombre con el que se le conoce en el plantel: es fiable.
        if (isset(self::ALIAS_AREAS[$clave])) {
            $id = array_search(self::ALIAS_AREAS[$clave], $centros, true);
            if ($id) {
                $pendientes[$clave] = ['texto' => $area, 'sugerido' => (int) $id, 'pista' => null,
                    'motivo' => 'Propuesto por su nombre en el plantel.'];
                return (int) $id;
            }
        }

        $mejor = Emparejador::mejorCoincidencia($area, $centros);
        $pendientes[$clave] = ['texto' => $area, 'sugerido' => null, 'pista' => $mejor['id'] ?? null,
            'motivo' => 'No se reconoció el laboratorio.'];

        return null;
    }

    protected function resolverMateria(string $materia, array $materias, array &$pendientes)
    {
        $clave = Emparejador::normalizar($materia);

        if ($decidido = $this->decidido('materia', $clave, $materia, $pendientes)) {
            return $decidido === -1 ? null : $decidido;
        }

        $aprendidas = $this->aprendidas('materia');
        if (isset($aprendidas[$clave])) {
            return (int) $aprendidas[$clave];
        }

        $mejor = Emparejador::mejorCoincidencia($materia, $materias);

        if ($mejor && $mejor['seguro']) {
            return (int) $mejor['id'];
        }

        // La misma materia dos veces en el catálogo: se propone la primera, pero
        // se enseña para que se elija la clave correcta.
        if ($mejor && $mejor['duplicado']) {
            $pendientes[$clave] = ['texto' => $materia, 'sugerido' => (int) $mejor['id'], 'pista' => null,
                'motivo' => 'Está repetida en el catálogo con claves distintas: elige cuál.'];
            return (int) $mejor['id'];
        }

        $pendientes[$clave] = ['texto' => $materia, 'sugerido' => null, 'pista' => $mejor['id'] ?? null,
            'motivo' => $mejor ? 'No hay una igual en el catálogo.' : 'No se parece a ninguna del catálogo.'];

        return null;
    }

    protected function resolverDocente(string $docente, $usuarios, array &$pendientes)
    {
        $clave = Emparejador::normalizar($docente);

        if ($clave === '') {
            $pendientes[''] = ['texto' => '(sin docente en el archivo)', 'sugerido' => null, 'pista' => null,
                'motivo' => 'Hay clases sin docente en el archivo.'];
            return null;
        }

        if ($decidido = $this->decidido('docente', $clave, $docente, $pendientes)) {
            return $decidido === -1 ? null : $decidido;
        }

        $aprendidas = $this->aprendidas('docente');
        if (isset($aprendidas[$clave])) {
            return (int) $aprendidas[$clave];
        }

        // Por las claves que se pueden armar con su nombre: EMEZA, JMEZA, EDELAROSA...
        $coincidencias = [];
        foreach ($usuarios as $usuario) {
            $claves = Emparejador::clavesDeDocente(
                $usuario->name,
                $usuario->apellido_paterno,
                $usuario->apellido_materno
            );

            if (in_array($clave, $claves, true)) {
                $coincidencias[] = $usuario->id;
            }
        }

        if (count($coincidencias) === 1) {
            return (int) $coincidencias[0];
        }

        $pendientes[$clave] = [
            'texto'    => $docente,
            'sugerido' => null,
            'pista'    => $coincidencias[0] ?? null,
            'motivo'   => count($coincidencias) > 1
                ? 'Hay varias personas a las que les corresponde esa clave.'
                : 'No se encontró a quién corresponde esa clave.',
        ];

        return null;
    }

    /**
     * Lo que la persona eligió en esta revisión. Devuelve el id elegido, -1 si
     * eligió "No importar", o null si todavía no ha decidido nada.
     *
     * Lo elegido se sigue enseñando en la revisión, para poder cambiarlo antes de
     * guardar.
     */
    protected function decidido(string $tipo, string $clave, string $texto, array &$pendientes)
    {
        if (! array_key_exists($clave, $this->decisiones[$tipo] ?? [])) {
            return null;
        }

        $id = (int) $this->decisiones[$tipo][$clave];

        $pendientes[$clave] = ['texto' => $texto, 'sugerido' => $id ?: null, 'pista' => null,
            'motivo' => $id ? 'Elegido en esta revisión.' : 'Marcado para no importarse.'];

        return $id > 0 ? $id : -1;
    }

    /**
     * El grupo del sistema para esta clase, creándolo en el plan si no existe.
     *
     * Los grupos se llaman "1EV-Introducción a la Programación". El archivo trae
     * el "1EV" y el nombre de la materia, a veces abreviado, así que se busca
     * entre los grupos de ese código por parecido antes de proponer uno nuevo.
     */
    protected function resolverGrupo(array $clase, array $materias, array &$nuevos)
    {
        $codigo = trim($clase['grupo']);
        $nombreMateria = $clase['materia_id'] ? ($materias[$clase['materia_id']] ?? $clase['materia']) : $clase['materia'];
        $nombrePropuesto = $codigo . '-' . $nombreMateria;

        $candidatos = Grupo::where('semestre_id', $this->semestreId)
            ->where('nombre_grupo', 'like', $codigo . '-%')
            ->pluck('nombre_grupo', 'id')
            ->all();

        foreach ($candidatos as $id => $nombre) {
            $sinCodigo = preg_replace('/^' . preg_quote($codigo, '/') . '-/i', '', $nombre);
            if (Emparejador::parecido($sinCodigo, $clase['materia']) >= 0.85
                || Emparejador::parecido($sinCodigo, $nombreMateria) >= 0.85) {
                return (int) $id;
            }
        }

        $nuevos[] = $nombrePropuesto;

        return null;   // se creará al confirmar
    }

    // ------------------------------------------------------------------
    //  Estado actual del sistema
    // ------------------------------------------------------------------

    /** Clases fijas del semestre en los laboratorios que toca el archivo. */
    protected function horariosExistentes(array $centros): array
    {
        if (empty($centros)) {
            return [];
        }

        $horarios = Horario::with(['materia', 'grupo', 'user', 'centroComputo'])
            ->where('semestre_id', $this->semestreId)
            ->whereIn('centro_computo_id', $centros)
            ->whereNull('fecha_especial')      // las reservas de un día no se tocan
            ->get();

        $porLlave = [];

        foreach ($horarios as $horario) {
            $llave = $this->llave(
                $horario->centro_computo_id,
                $horario->grupo_id,
                $horario->materia_id,
                $horario->dia_semana
            );

            $porLlave[$llave][] = $horario;
        }

        return $porLlave;
    }

    protected function llave($centro, $grupo, $materia, $dia): string
    {
        return $centro . '|' . $grupo . '|' . $materia . '|' . $dia;
    }

    protected function describir(Horario $horario): string
    {
        $materia = $horario->materia->nombre_materia ?? 'Sin materia';
        $grupo   = $horario->grupo->nombre_grupo ?? 'sin grupo';
        $centro  = $horario->centroComputo->nombre_centro ?? '';

        return $materia . ' (' . $grupo . ') — ' . $horario->dia_semana . ' de '
            . substr($horario->hora_inicio, 0, 5) . ' a ' . substr($horario->hora_fin, 0, 5)
            . ($centro ? ' en ' . $centro : '');
    }

    /** Clases del propio archivo que se encimarían en el mismo laboratorio. */
    protected function buscarEmpalmes(array $clases): array
    {
        $empalmes = [];

        // El laboratorio de cada clase: el del sistema si ya se sabe; si no, el
        // área tal como viene en el archivo. Comparar dos "no se sabe" como si
        // fueran el mismo salón daba empalmes falsos entre CAD 1 y CEC.
        $laboratorio = function ($clase) {
            return $clase['centro_id'] ? 'id:' . $clase['centro_id'] : 'area:' . Emparejador::normalizar($clase['area']);
        };

        foreach ($clases as $i => $a) {
            foreach (array_slice($clases, $i + 1) as $b) {
                if ($laboratorio($a) !== $laboratorio($b) || $a['dia'] !== $b['dia']) {
                    continue;
                }

                if ($a['inicio'] < $b['fin'] && $a['fin'] > $b['inicio']) {
                    $empalmes[] = $a['dia'] . ' de ' . substr($a['inicio'], 0, 5) . ' a ' . substr($a['fin'], 0, 5)
                        . ': ' . $a['materia'] . ' (' . $a['grupo'] . ') con '
                        . $b['materia'] . ' (' . $b['grupo'] . ')';
                }
            }
        }

        return $empalmes;
    }

    /**
     * Clases del archivo que se encimarían con algo que ya está en el sistema y
     * que el archivo no trae: otra clase que quedó como sobrante, o una reserva
     * de un día concreto. Son avisos, no impiden importar.
     */
    protected function empalmesConElSistema(array $clases, array $centros, array $usados): array
    {
        if (empty($centros)) {
            return [];
        }

        $otros = Horario::with(['materia', 'grupo'])
            ->where('semestre_id', $this->semestreId)
            ->whereIn('centro_computo_id', $centros)
            ->whereNotIn('id', $usados ?: [0])
            ->get();

        $avisos = [];

        foreach ($clases as $clase) {
            if ($clase['estado'] === 'pendiente' || $clase['estado'] === 'igual') {
                continue;
            }

            foreach ($otros as $otro) {
                if ((int) $otro->centro_computo_id !== (int) $clase['centro_id']
                    || $otro->dia_semana !== $clase['dia']) {
                    continue;
                }

                if (substr($otro->hora_inicio, 0, 8) < $clase['fin'] && substr($otro->hora_fin, 0, 8) > $clase['inicio']) {
                    $avisos[] = $clase['materia'] . ' (' . $clase['grupo'] . ') el ' . $clase['dia']
                        . ' de ' . substr($clase['inicio'], 0, 5) . ' a ' . substr($clase['fin'], 0, 5)
                        . ' se encimaría con: ' . $this->describir($otro);
                }
            }
        }

        return $avisos;
    }

    /**
     * Guarda el plan: crea, actualiza y (si se pidió) manda a la papelera.
     *
     * Devuelve el conteo de lo que hizo. Quien llama se encarga de envolverlo en
     * una transacción, para que sea todo o nada.
     */
    public function aplicar(array $plan, bool $quitarSobrantes = false): array
    {
        $this->guardarAprendizaje();

        $hecho = ['creadas' => 0, 'actualizadas' => 0, 'iguales' => 0, 'grupos' => 0, 'retiradas' => 0, 'omitidas' => 0];

        foreach ($plan['clases'] as $clase) {
            if ($clase['estado'] === 'pendiente') {
                $hecho['omitidas']++;
                continue;
            }

            if ($clase['estado'] === 'igual') {
                $hecho['iguales']++;
                continue;
            }

            $grupoId = $clase['grupo_id'];

            if (! $grupoId) {
                $grupo = Grupo::firstOrCreate([
                    'nombre_grupo' => $this->nombreDeGrupo($clase),
                    'semestre_id'  => $this->semestreId,
                ]);

                if ($grupo->wasRecentlyCreated) {
                    $hecho['grupos']++;
                }

                $grupoId = $grupo->id;
            }

            if ($clase['estado'] === 'cambia' && $clase['horario_id']) {
                $horario = Horario::find($clase['horario_id']);

                if ($horario) {
                    $horario->hora_inicio = $clase['inicio'];
                    $horario->hora_fin    = $clase['fin'];
                    $horario->user_id     = $clase['docente_id'];
                    $horario->save();
                    $hecho['actualizadas']++;
                }

                continue;
            }

            Horario::create([
                'tipo_reserva'      => 'recurrente',
                'user_id'           => $clase['docente_id'],
                'materia_id'        => $clase['materia_id'],
                'grupo_id'          => $grupoId,
                'centro_computo_id' => $clase['centro_id'],
                'dia_semana'        => $clase['dia'],
                'hora_inicio'       => $clase['inicio'],
                'hora_fin'          => $clase['fin'],
                'semestre_id'       => $this->semestreId,
                'fecha_especial'    => null,
            ]);

            $hecho['creadas']++;
        }

        if ($quitarSobrantes && ! empty($plan['sobrantes'])) {
            $ids = array_column($plan['sobrantes'], 'id');
            $hecho['retiradas'] = Horario::whereIn('id', $ids)->delete();   // a la papelera
        }

        return $hecho;
    }

    /** "1EV-Introducción a la Programación", con el nombre oficial de la materia. */
    protected function nombreDeGrupo(array $clase): string
    {
        $materia = $clase['materia_id']
            ? (Materia::find($clase['materia_id'])->nombre_materia ?? $clase['materia'])
            : $clase['materia'];

        return trim($clase['grupo']) . '-' . $materia;
    }

    /** Deja aprendidas las equivalencias que la persona confirmó. */
    protected function guardarAprendizaje(): void
    {
        foreach (['area', 'materia', 'docente'] as $tipo) {
            foreach ($this->decisiones[$tipo] ?? [] as $clave => $destinoId) {
                if ($destinoId) {
                    EquivalenciaImportacion::aprender($tipo, $clave, $clave, (int) $destinoId);
                }
            }
        }
    }

    /** Equivalencias ya aprendidas de un tipo, en memoria para no repetir consultas. */
    protected function aprendidas(string $tipo): array
    {
        static $cache = [];

        if (! isset($cache[$tipo])) {
            $cache[$tipo] = EquivalenciaImportacion::mapa($tipo);
        }

        return $cache[$tipo];
    }
}
