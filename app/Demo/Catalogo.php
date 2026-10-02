<?php

namespace App\Demo;

/**
 * Los datos de partida de la demostración: laboratorios, generaciones, clases y
 * los nombres con los que se arman las personas. Todo es inventado.
 */
class Catalogo
{
    /** nombre, PCs, ¿permite uso libre? */
    const LABORATORIOS = [
        ['CAD 1', 25, true],
        ['CAD 2', 25, false],
        ['CEC', 30, true],
        ['CCNA', 20, false],
    ];

    const CARRERAS = [
        'S' => 'Ingeniería en Sistemas Computacionales',
        'I' => 'Ingeniería Industrial',
        'Q' => 'Arquitectura',
        'G' => 'Ingeniería en Gestión Empresarial',
        'A' => 'Licenciatura en Administración',
        'E' => 'Ingeniería Electromecánica',
        'K' => 'Contador Público',
    ];

    /**
     * Las generaciones: clave => número de alumnos. La clave es semestre + carrera
     * + turno (M matutino, V vespertino): "5SM" es quinto de Sistemas por la mañana.
     */
    const GENERACIONES = [
        '1SM' => 28, '3SM' => 26, '5SM' => 24, '7SM' => 22, '1IM' => 27, '3IM' => 25, '5QM' => 20,
        '1GV' => 26, '3AV' => 24, '1EV' => 25, '3KV' => 23, '5SV' => 21,
    ];

    /** La generación del alumno de demostración y la clase que comparte con el profesor de demostración. */
    const GENERACION_DEL_ALUMNO_DEMO = '5SM';

    /**
     * Las clases del semestre: generación, materia, laboratorio y profesor (su
     * posición en la lista de profesores; el 0 es el de demostración). Cada una
     * se imparte dos veces por semana, dos horas.
     */
    const CLASES = [
        ['1SM', 'Fundamentos de Programación', 'CAD 1', 0],
        ['1SM', 'Matemáticas Discretas', 'CEC', 1],
        ['3SM', 'Estructura de Datos', 'CAD 1', 2],
        ['3SM', 'Programación Orientada a Objetos', 'CEC', 3],
        ['5SM', 'Taller de Bases de Datos', 'CAD 1', 0],
        ['5SM', 'Redes de Computadoras', 'CCNA', 4],
        ['5SM', 'Simulación', 'CEC', 1],
        ['7SM', 'Programación Web', 'CAD 1', 11],
        ['7SM', 'Conmutación y Enrutamiento de Redes', 'CCNA', 4],
        ['1IM', 'Dibujo Industrial', 'CAD 2', 5],
        ['1IM', 'Algoritmos y Lenguajes de Programación', 'CEC', 2],
        ['3IM', 'Estadística Inferencial I', 'CEC', 6],
        ['3IM', 'Investigación de Operaciones I', 'CAD 2', 6],
        ['5QM', 'Taller de Diseño Asistido por Computadora', 'CAD 2', 7],
        ['5QM', 'Geometría Descriptiva II', 'CAD 2', 7],
        ['1GV', 'Software de Aplicación Ejecutivo', 'CAD 1', 8],
        ['1GV', 'Fundamentos de Investigación', 'CEC', 9],
        ['3AV', 'Informática para la Administración', 'CEC', 8],
        ['3AV', 'Estadística para la Administración', 'CAD 1', 9],
        ['1EV', 'Dibujo Electromecánico', 'CAD 2', 5],
        ['1EV', 'Introducción a la Programación', 'CAD 1', 3],
        ['3KV', 'Costos Empresariales', 'CEC', 10],
        ['3KV', 'Software Contable', 'CAD 1', 10],
        ['5SV', 'Taller de Bases de Datos', 'CAD 1', 0],
        ['5SV', 'Administración de Redes', 'CCNA', 11],
        ['5SV', 'Programación Web', 'CEC', 2],
    ];

    const ACADEMIAS = ['Sistemas y Computación', 'Ciencias Básicas', 'Ingeniería Industrial', 'Ciencias Económico-Administrativas', 'Arquitectura'];

    const NOMBRES = [
        'Alejandro', 'María Fernanda', 'José Luis', 'Ana Sofía', 'Carlos', 'Valeria', 'Miguel Ángel', 'Daniela', 'Jorge', 'Paola',
        'Luis Fernando', 'Andrea', 'Ricardo', 'Karla', 'Eduardo', 'Mariana', 'Fernando', 'Brenda', 'Héctor', 'Diana',
        'Roberto', 'Alejandra', 'Sergio', 'Gabriela', 'Óscar', 'Laura', 'Iván', 'Natalia', 'Raúl', 'Itzel',
        'Emmanuel', 'Ximena', 'Diego', 'Regina', 'Adrián', 'Camila', 'Marco Antonio', 'Jimena', 'Víctor', 'Estefanía',
        'Kevin', 'Fátima', 'Brandon', 'Rocío', 'Cristian', 'Melissa', 'Ángel', 'Perla', 'Julio César', 'Abigail',
    ];

    const APELLIDOS = [
        'García', 'Hernández', 'Martínez', 'López', 'González', 'Pérez', 'Rodríguez', 'Sánchez', 'Ramírez', 'Cruz',
        'Flores', 'Gómez', 'Morales', 'Vázquez', 'Jiménez', 'Reyes', 'Díaz', 'Torres', 'Gutiérrez', 'Ruiz',
        'Mendoza', 'Aguilar', 'Ortiz', 'Castillo', 'Romero', 'Álvarez', 'Chávez', 'Rivera', 'Juárez', 'Ramos',
        'Domínguez', 'Herrera', 'Medina', 'Castro', 'Vargas', 'Guzmán', 'Velázquez', 'Rojas', 'Salazar', 'Contreras',
        'Luna', 'Ortega', 'Estrada', 'Cervantes', 'Navarro', 'Ibarra', 'Meza', 'Valenzuela', 'Camacho', 'Espinoza',
    ];

    /** Lo que reportan los alumnos: categoría => descripciones posibles. */
    const FALLAS = [
        'Mouse' => ['El mouse no responde, parece desconectado.', 'El botón izquierdo del mouse falla a veces.', 'El cursor se mueve solo.'],
        'Teclado' => ['Varias teclas no funcionan (E, R y la barra espaciadora).', 'El teclado escribe letras repetidas.'],
        'Monitor' => ['La pantalla parpadea a los pocos minutos.', 'El monitor se ve con una franja de color en un lado.'],
        'Internet' => ['No carga ninguna página, pero las PCs de al lado sí tienen internet.', 'La conexión se cae cada cierto tiempo.'],
        'Software' => ['No abre el programa de la práctica, marca un error de licencia.', 'Falta instalar el compilador que pide el profesor.'],
        'Encendido' => ['La computadora no enciende.', 'Se apaga sola a los pocos minutos de usarla.'],
        'Otro' => ['La silla de este lugar está rota.', 'El puerto USB del frente no reconoce la memoria.'],
    ];

    const SOLUCIONES = [
        'Se reemplazó el periférico por uno nuevo del almacén.',
        'Se reconectó el cable y se probó el equipo: funciona correctamente.',
        'Se reinstaló el programa y se verificó con una práctica de prueba.',
        'Se cambió el cable de red y se revisó el nodo.',
        'Se actualizó el controlador y se reinició el equipo.',
        'Se revisó y no se pudo reproducir la falla; el equipo queda en observación.',
    ];

    const JUSTIFICANTES = ['Presentó justificante médico.', 'Comisión deportiva del plantel.', 'Trámite escolar autorizado por la coordinación.'];

    const MOTIVOS_DEL_PROFESOR = ['Comisión académica fuera del plantel.', 'Incapacidad médica.', 'Reunión de academia.'];
}
