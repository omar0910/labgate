{{--
    Estilos de asistencia compartidos: el anillo del porcentaje, los puntos de
    las últimas clases y los colores de cada estado. Los usan "Mi Progreso" del
    alumno (App\Support\ProgresoDelAlumno::ESTADOS) y el historial, las
    estadísticas y los reportes del profesor (App\Support\ClasesDelProfesor::ESTADOS).
--}}
@once
    <style>
        .hover-lift {
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
        }

        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 .5rem 1rem rgba(0, 0, 0, .15) !important;
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Anillo con el porcentaje (verde si está al día, rojo si está en riesgo) */
        .anillo-progreso {
            --valor: 0;
            --color: var(--marca-green);
            --tamano: 110px;
            width: var(--tamano);
            height: var(--tamano);
            flex-shrink: 0;
            border-radius: 50%;
            display: grid;
            place-items: center;
            background: conic-gradient(var(--color) calc(var(--valor) * 1%), #e9ecef 0);
        }

        .anillo-progreso::before {
            content: '';
            grid-area: 1 / 1;
            width: 76%;
            height: 76%;
            border-radius: 50%;
            background: #fff;
        }

        .anillo-progreso > span {
            grid-area: 1 / 1;
            position: relative;
            font-weight: 700;
            font-size: calc(var(--tamano) * 0.22);
            color: var(--color);
        }

        .anillo-progreso.en-riesgo {
            --color: #dc3545;
        }

        /* Puntos de las últimas clases */
        .punto-clase {
            display: inline-block;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            border: 2px solid transparent;
        }

        .punto-clase.asistio { background: var(--marca-green); }
        .punto-clase.justificada { background: #d39e00; }
        .punto-clase.falta { background: #dc3545; }
        .punto-clase.sin-registro { background: #fff; border-color: #dc3545; }
        .punto-clase.no-impartida { background: #ced4da; }

        /* Etiqueta de estado de cada clase */
        .estado-clase {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            border-radius: 50rem;
            padding: .25rem .75rem;
            font-size: .8rem;
            font-weight: 600;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .estado-clase.asistio { color: var(--marca-green); background: rgba(0, 155, 77, .1); border-color: rgba(0, 155, 77, .25); }
        .estado-clase.justificada { color: #b58800; background: rgba(255, 193, 7, .12); border-color: rgba(255, 193, 7, .35); }
        .estado-clase.falta { color: #dc3545; background: rgba(220, 53, 69, .1); border-color: rgba(220, 53, 69, .25); }
        .estado-clase.sin-registro { color: #dc3545; background: #fff; border: 1px dashed rgba(220, 53, 69, .6); }
        .estado-clase.no-impartida { color: #6c757d; background: #f1f3f5; border-color: #dee2e6; }
    </style>
@endonce
