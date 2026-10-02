<?php

/*
|--------------------------------------------------------------------------
| Identidad de la instalación
|--------------------------------------------------------------------------
|
| Nombre del sistema, institución, logotipos y dominio de sus correos. Todo lo
| que el sistema muestra con el nombre de la institución (encabezados, correos,
| documentos PDF, pantalla de acceso) sale de aquí.
|
| Para adaptarlo a otra institución basta con las variables del .env y con
| reemplazar las dos imágenes de public/img.
|
*/

return [

    // El sistema
    'sistema' => env('MARCA_SISTEMA', 'LabGate'),
    'lema' => env('MARCA_LEMA', 'Control de acceso y asistencia en laboratorios de cómputo'),

    // La institución. Las dos líneas son como se parte el nombre en el encabezado.
    'institucion' => env('MARCA_INSTITUCION', 'Instituto Tecnológico Demo'),
    'institucion_linea_1' => env('MARCA_INSTITUCION_LINEA_1', 'Instituto Tecnológico'),
    'institucion_linea_2' => env('MARCA_INSTITUCION_LINEA_2', 'Demo'),
    'siglas' => env('MARCA_SIGLAS', 'ITD'),

    // Con el que firman los correos y los documentos
    'area' => env('MARCA_AREA', 'Centro de Cómputo'),

    // Dominio de los correos que se arman solos al importar alumnos y profesores
    // (matrícula o usuario + @dominio).
    'dominio_correo' => env('MARCA_DOMINIO_CORREO', 'instituto-demo.test'),

    // Imágenes, relativas a public/. La de documentos va en los PDF y necesita
    // fondo blanco; la otra va sobre el encabezado oscuro.
    'logo' => 'img/logo.png',
    'logo_documentos' => 'img/logo-documentos.png',

];
