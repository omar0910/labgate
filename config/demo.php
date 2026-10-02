<?php

/*
|--------------------------------------------------------------------------
| Modo demostración
|--------------------------------------------------------------------------
|
| Para publicar el sistema con datos ficticios y que cualquiera pueda entrar a
| probarlo. Con DEMO=true:
|
|   - La pantalla de acceso ofrece entrar con un clic como cada uno de los roles.
|   - Se bloquean las acciones que dejarían la demostración inservible para el
|     siguiente visitante (ver App\Http\Middleware\ModoDemo).
|   - Una tarea simula la actividad del día (alumnos que se registran en clase,
|     entradas y salidas de uso libre) y otra reinicia los datos cada noche.
|
| En una instalación real se deja apagado y nada de esto existe.
|
*/

return [

    'activo' => (bool) env('DEMO', false),

    // Las cuentas con las que se entra desde la pantalla de acceso. El sembrador
    // (Database\Seeders\DemoSeeder) las crea con estos usuarios.
    'cuentas' => [
        'Administrador' => 'admin.demo',
        'Encargado'     => 'encargado.demo',
        'Profesor'      => 'profesor.demo',
        'Alumno'        => 'alumno.demo',
    ],

    // Contraseña de esas cuentas, por si alguien prefiere escribirla
    'contrasena' => env('DEMO_CONTRASENA', 'demo1234'),

    // Hora (de la zona horaria de la aplicación) a la que se reinician los datos
    'hora_de_reinicio' => env('DEMO_HORA_REINICIO', '04:00'),

];
