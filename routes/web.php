<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\MateriaController;
use App\Http\Controllers\CentroComputoController;
use App\Http\Controllers\SemestreController;
use App\Http\Controllers\AlumnoController;
use App\Http\Controllers\GrupoController;
use App\Http\Controllers\GrupoAlumnoController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\HorariosImportacionController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AlumnoDashboardController;
use App\Http\Controllers\EncargadoController;
use App\Http\Controllers\ProfesorController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MonitorController;
use App\Http\Controllers\IncidenciaController;
use App\Http\Controllers\ClaseController;
use Illuminate\Support\Facades\Mail;
use App\Http\Controllers\DiaInhabilController;
use App\Http\Controllers\BackupController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/
//Prueba de ramas con git
//=====================================================
// 1. RUTAS DE AUTENTICACIÓN
//=====================================================
Route::get('/', function () {
    return redirect()->route('login');
});

// Con 'guest': a quien ya tiene su sesión abierta no se le vuelve a enseñar el
// formulario, se le manda a su panel. Pasaba en los equipos del laboratorio, que
// abren el sistema solos: el alumno que acababa de registrarse veía otra vez la
// pantalla de acceso aunque no hubiera cerrado sesión.
Route::get('login', [LoginController::class, 'showLoginForm'])->middleware('guest')->name('login');
Route::post('login', [LoginController::class, 'login'])->name('login.post');
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// Entrar a la demostración con un clic. Sólo responde con DEMO=true.
Route::post('demo/entrar/{rol}', [App\Http\Controllers\DemoController::class, 'entrar'])
    ->middleware(['guest', 'throttle:30,1'])->name('demo.entrar');

//=====================================================
// 2. RUTA MAESTRA DE REDIRECCIÓN (El "Semáforo")
//=====================================================
Route::get('/redirect', function () {
    $rol = Auth::user()->rol;

    switch ($rol) {
        case 'Administrador':
            return redirect()->route('admin.dashboard');
        case 'Encargado':
            return redirect()->route('encargado.inicio');
        case 'Profesor':
            return redirect()->route('profesor.dashboard');
        case 'Alumno':
            return redirect()->route('alumno.dashboard');
        default:
            Auth::logout();
            return redirect('/login')->with('error', 'Rol no reconocido.');
    }
})->middleware('auth')->name('dashboard_redirect');


//=====================================================
// 3. PERFIL DE USUARIO (Accesible para TODOS)
//=====================================================
Route::middleware('auth')->group(function () {
    Route::get('perfil', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('perfil/password', [ProfileController::class, 'updatePassword'])->name('profile.update-password');
    Route::post('perfil/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/photo/delete', [ProfileController::class, 'deletePhoto'])->name('profile.photo.delete');
});


//=====================================================
// 3.5. RUTAS COMPARTIDAS (Monitor en Vivo)
//=====================================================
// Estas rutas están fuera de los grupos exclusivos para que AMBOS roles puedan entrar
// sin que el middleware 'role:Administrador' bloquee al Encargado.
Route::middleware(['auth'])->group(function () {

    // Sólo Administrador o Encargado. Antes era una función escrita aquí mismo, y
    // con ella "php artisan route:cache" (u "optimize") generaba un archivo de rutas
    // roto que dejaba caído todo el sistema. El filtro de roles ya acepta varios.
    Route::middleware('role:Administrador,Encargado')->group(function () {

        // Rutas unificadas del Monitor
        // NOTA: Como cambiamos el nombre a 'monitor.index', asegúrate de actualizar
        // los enlaces en tus vistas (sidebar) para que apunten a route('monitor.index')
        Route::get('/monitor/vivo', [MonitorController::class, 'index'])->name('monitor.index');
        Route::get('/monitor/vivo/{id}', [MonitorController::class, 'show'])->name('monitor.show');
        Route::post('/monitor/vivo/{id}/pc/{maquina}/cerrar', [MonitorController::class, 'forzarSalida'])->name('monitor.cerrar_sesion');
        Route::get('/monitor/buscar-alumno', [App\Http\Controllers\MonitorController::class, 'buscarAlumnoPorMatricula'])->name('monitor.buscar_alumno');
        Route::post('/monitor/asignar-uso-libre', [App\Http\Controllers\MonitorController::class, 'asignarUsoLibre'])->name('monitor.asignar_uso_libre');
        Route::post('/monitor/asignar-uso-libre-historico', [App\Http\Controllers\MonitorController::class, 'asignarUsoLibreHistorico'])->name('monitor.asignar_uso_libre_historico');
        Route::post('/monitor/mantenimiento', [MonitorController::class, 'enviarMantenimiento'])->name('monitor.mantenimiento');

        // ==========================================
        // MÓDULO DE BITÁCORA DE CLASES Y ASISTENCIAS
        // ==========================================
        // Va DENTRO de este subgrupo a propósito: la bitácora deja pasar lista y
        // corregir asistencias, así que es solo para Administrador y Encargado.
        // Los profesores tienen su propio pase de lista en su panel.
        Route::prefix('bitacora')->name('admin.bitacora.')->group(function () {
            // 1. Buscador de Clases
            Route::get('/', [App\Http\Controllers\BitacoraController::class, 'index'])->name('index');

            // 2. Historial de la Clase
            Route::get('/clase/{id}', [App\Http\Controllers\BitacoraController::class, 'show'])->name('show');

            // 3. Ver lista de alumnos de una fecha
            // La fecha tiene que venir como 2026-09-24: con otra cosa la página tronaba.
            Route::get('/clase/{id}/asistencia/{fecha}', [App\Http\Controllers\BitacoraController::class, 'asistencia'])
                ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('asistencia');

            // 4. Guardar la lista de asistencia
            Route::post('/clase/{id}/asistencia/{fecha}', [App\Http\Controllers\BitacoraController::class, 'storeAsistencia'])
                ->where('fecha', '\d{4}-\d{2}-\d{2}')->name('store');

            // 5. Clases pasadas sin asistencia del profesor, para capturarlas de las hojas
            Route::get('/pendientes', [App\Http\Controllers\ClasesPendientesController::class, 'index'])->name('pendientes');
            Route::post('/pendientes', [App\Http\Controllers\ClasesPendientesController::class, 'registrar'])->name('pendientes.registrar');
            Route::post('/pendientes/deshacer', [App\Http\Controllers\ClasesPendientesController::class, 'deshacer'])->name('pendientes.deshacer');
        });
    });
});


//=====================================================
// 4. ZONA ADMINISTRADOR (Protegida con role:Administrador)
//=====================================================
Route::middleware(['auth', 'role:Administrador'])->prefix('admin')->group(function () {

    Route::get('dashboard', [AdminDashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('semana', [AdminDashboardController::class, 'semana'])->name('admin.semana');

    // GESTIÓN DE CLASES Y ASISTENCIA (Panel Admin)
    // La agenda del día vive en 'admin.reportes.clases-hoy' (más abajo); aquí había una
    // ruta 'admin.clases-hoy' apuntando a un método inexistente, que devolvía error 500.
    Route::post('clase/{id}/estado', [AdminDashboardController::class, 'registrarEstadoClase']);
    Route::get('clase/{id}/asistencia', [AdminDashboardController::class, 'verAsistenciaClase'])->name('admin.ver-asistencia');

    // REPORTES DASHBOARD
    Route::get('reportes/profesores', [AdminDashboardController::class, 'reporteProfesores'])->name('admin.reportes.profesores');
    Route::get('reportes/clases-hoy', [AdminDashboardController::class, 'reporteClasesHoy'])->name('admin.reportes.clases-hoy');
    Route::get('reportes/uso-libre', [AdminDashboardController::class, 'reporteUsoLibre'])->name('admin.reportes.uso-libre');
    Route::delete('/reportes/uso-libre/{id}', [AdminDashboardController::class, 'destroyUsoLibre'])->name('admin.reportes.uso-libre.destroy');

    // CRUDs PRINCIPALES
    // Se excluye 'show': ninguno de estos módulos tiene vista de detalle individual
    // (se gestionan desde el listado y el formulario de edición), y dejar la ruta
    // registrada sin método en el controlador provocaba un error 500.
    Route::resource('materias', MateriaController::class)->except(['show']);
    Route::resource('centros-computo', CentroComputoController::class)->parameters(['centros-computo' => 'centro_computo'])->except(['show']);
    Route::resource('semestres', SemestreController::class)->except(['show']);
    Route::resource('alumnos', AlumnoController::class)->except(['show']);
    Route::resource('grupos', GrupoController::class)->except(['show']);
    Route::resource('usuarios', UserController::class)->except(['show']);
    Route::resource('horarios', HorarioController::class)->except(['show']);

    // GESTIÓN DE GRUPOS
    Route::get('grupos/{grupo}/gestionar-alumnos', [GrupoAlumnoController::class, 'index'])->name('grupos.gestionar-alumnos');
    Route::post('grupos/{grupo}/gestionar-alumnos', [GrupoAlumnoController::class, 'store'])->name('grupos.sincronizar-alumnos');
    Route::post('/grupos/{grupo}/importar', [GrupoAlumnoController::class, 'importarAlumnos'])->name('grupos.importar-excel');

    //IMPORTADOR DE GRUPOS (crea las listas de clase desde el Excel de horarios)
    Route::post('/grupos/importar', [GrupoController::class, 'importar'])->name('grupos.importar');

    //IMPORTADOR DE MATERIAS
    Route::post('/materias/importar', [MateriaController::class, 'importar'])->name('materias.importar');

    //IMPORTADOR EN ALUMNOS MAESTRA
    Route::post('/alumnos/importar', [AlumnoController::class, 'importar'])->name('alumnos.importar');

    // DAR DE BAJA Y REACTIVAR: "eliminar" ya no borra (se perdía el historial), da de baja
    Route::post('/alumnos/{alumno}/reactivar', [AlumnoController::class, 'reactivar'])->name('alumnos.reactivar');
    Route::post('/usuarios/{usuario}/reactivar', [UserController::class, 'reactivar'])->name('usuarios.reactivar');

    //IMPORTADOR DE PROFESORES
    // NOTA: este grupo ya lleva prefix('admin'), así que las URIs NO deben repetir /admin.
    Route::post('/profesores/importar', [App\Http\Controllers\AdminDashboardController::class, 'importarProfesores'])->name('admin.profesores.importar');

    //RUTAS DE BTN NUEVO EN PROFESORES
    Route::get('/profesores/crear', [App\Http\Controllers\AdminDashboardController::class, 'createProfesor'])->name('admin.profesores.create');
    Route::post('/profesores/guardar', [App\Http\Controllers\AdminDashboardController::class, 'storeProfesor'])->name('admin.profesores.store');

    //RUTAS DE EDITAR EN PROFESORES
    Route::get('/profesores/{profesor}/editar', [App\Http\Controllers\AdminDashboardController::class, 'editProfesor'])->name('admin.profesores.edit');
    Route::put('/profesores/{profesor}', [App\Http\Controllers\AdminDashboardController::class, 'updateProfesor'])->name('admin.profesores.update');

    Route::delete('/profesores/{usuario}', [App\Http\Controllers\UserController::class, 'destroy'])->name('admin.profesores.destroy');

    // IMPORTADOR DE HORARIOS (la rejilla de horarios de los laboratorios)
    // Primero se revisa el archivo y se enseña lo que pasaría; sólo al confirmar se guarda.
    Route::post('horarios/importar/revisar', [HorariosImportacionController::class, 'revisar'])->name('horarios.importar.revisar');
    Route::post('horarios/importar/confirmar', [HorariosImportacionController::class, 'confirmar'])->name('horarios.importar.confirmar');

    // HORARIOS (Papelera)
    Route::get('horarios/papelera/ver', [HorarioController::class, 'papelera'])->name('horarios.papelera');
    Route::post('horarios/{id}/restaurar', [HorarioController::class, 'restaurar'])->name('horarios.restaurar');
    // Restaurar o eliminar varias a la vez, y eliminar para siempre (sólo sin asistencias)
    Route::post('horarios/papelera/acciones', [HorarioController::class, 'accionesPapelera'])->name('horarios.papelera.acciones');
    Route::delete('horarios/{id}/definitivo', [HorarioController::class, 'eliminarDefinitivo'])->name('horarios.eliminarDefinitivo');

    // CENTROS COMPUTO EXTRA: el formulario de edición guarda con esta ruta. (Había
    // también un GET .../{id}/edit duplicado que nunca se usaba: la ruta del recurso,
    // registrada antes, atiende siempre esa dirección.)
    Route::put('centros-computo/{id}', [CentroComputoController::class, 'update'])->name('admin.centros-computo.update');

    // MANTENIMIENTO E INCIDENCIAS
    Route::get('incidencias', [IncidenciaController::class, 'index'])->name('admin.incidencias.index');
    Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])->name('admin.incidencias.resolver');
    Route::get('/incidencias/historial', [IncidenciaController::class, 'historialCompleto'])->name('admin.incidencias.historial');

    Route::post('clase/{id}/liberar', [ClaseController::class, 'liberarClase'])->name('admin.clase.liberar');

    // REPORTES ANALÍTICOS
    Route::get('/reportes', [ReporteController::class, 'index'])->name('admin.reportes.index');
    Route::get('/reportes/docentes/pdf', [ReporteController::class, 'descargarDocentesPdf'])->name('admin.reportes.docentes.pdf');
    Route::get('/reportes/docentes/excel', [ReporteController::class, 'descargarDocentesExcel'])->name('admin.reportes.docentes.excel');

    // REPORTE DEL RESUMEN GENERAL (primera pestana)
    Route::get('/reportes/resumen/pdf', [ReporteController::class, 'descargarResumenPdf'])->name('admin.reportes.resumen.pdf');
    Route::get('/reportes/resumen/excel', [ReporteController::class, 'descargarResumenExcel'])->name('admin.reportes.resumen.excel');

    // REPORTES DE ALUMNOS
    Route::get('/reportes/alumnos/pdf', [ReporteController::class, 'descargarAlumnosPdf'])->name('admin.reportes.alumnos.pdf');
    Route::get('/reportes/alumnos/excel', [ReporteController::class, 'descargarAlumnosExcel'])->name('admin.reportes.alumnos.excel');

    // REPORTES DE INFRAESTRUCTURA (LABORATORIOS)
    Route::get('/reportes/laboratorios/pdf', [ReporteController::class, 'descargarLaboratoriosPdf'])->name('admin.reportes.laboratorios.pdf');
    Route::get('/reportes/laboratorios/excel', [ReporteController::class, 'descargarLaboratoriosExcel'])->name('admin.reportes.laboratorios.excel');
    Route::post('/equipos/mantenimiento-masivo', [App\Http\Controllers\ReporteController::class, 'mantenimientoMasivo'])->name('admin.equipos.mantenimiento-masivo');
    Route::get('/reportes/carreras-detalle', [App\Http\Controllers\ReporteController::class, 'reporteCarrerasDetalle'])->name('admin.reportes.carreras-detalle');
    //Rutas de reporte detallado de uso de labopratorios por carrera
    Route::get('/reportes/carreras-detalle/pdf', [App\Http\Controllers\ReporteController::class, 'descargarCarrerasDetallePdf'])->name('admin.reportes.carreras-detalle.pdf');
    Route::get('/reportes/carreras-detalle/excel', [App\Http\Controllers\ReporteController::class, 'descargarCarrerasDetalleExcel'])->name('admin.reportes.carreras-detalle.excel');
    // Rutas para el Récord Histórico Detallado (tirmpo de uso) en laboratorios y uso indivdual
    Route::get('/reportes/record-historico', [App\Http\Controllers\ReporteController::class, 'recordHistoricoDetalle'])->name('admin.reportes.record-historico');
    Route::get('/reportes/record-historico/pdf', [App\Http\Controllers\ReporteController::class, 'descargarRecordHistoricoPdf'])->name('admin.reportes.record-historico.pdf');
    Route::get('/reportes/record-historico/excel', [App\Http\Controllers\ReporteController::class, 'descargarRecordHistoricoExcel'])->name('admin.reportes.record-historico.excel');
    // Rutas para el Reporte detallado de Registro Físico de Hardware
    Route::get('/reportes/hardware-detalle', [App\Http\Controllers\ReporteController::class, 'hardwareDetalle'])->name('admin.reportes.hardware-detalle');
    Route::get('/reportes/hardware-detalle/pdf', [App\Http\Controllers\ReporteController::class, 'descargarHardwareDetallePdf'])->name('admin.reportes.hardware-detalle.pdf');
    Route::get('/reportes/hardware-detalle/excel', [App\Http\Controllers\ReporteController::class, 'descargarHardwareDetalleExcel'])->name('admin.reportes.hardware-detalle.excel');
    // Rutas para el Reporte Detallado de Uso Libre
    Route::get('/reportes/uso-libre-detalle', [App\Http\Controllers\ReporteController::class, 'usoLibreDetalle'])->name('admin.reportes.uso-libre-detalle');
    Route::get('/reportes/uso-libre-detalle/pdf', [App\Http\Controllers\ReporteController::class, 'descargarUsoLibrePdf'])->name('admin.reportes.uso-libre-detalle.pdf');
    Route::get('/reportes/uso-libre-detalle/excel', [App\Http\Controllers\ReporteController::class, 'descargarUsoLibreExcel'])->name('admin.reportes.uso-libre-detalle.excel');
    // Rutas para el Reporte Detallado de Materias/Clases
    Route::get('/reportes/materias-detalle', [App\Http\Controllers\ReporteController::class, 'materiasDetalle'])->name('admin.reportes.materias-detalle');
    Route::get('/reportes/materias-detalle/pdf', [App\Http\Controllers\ReporteController::class, 'descargarMateriasPdf'])->name('admin.reportes.materias-detalle.pdf');
    Route::get('/reportes/materias-detalle/excel', [App\Http\Controllers\ReporteController::class, 'descargarMateriasExcel'])->name('admin.reportes.materias-detalle.excel');

    //RUTAS PARA CALENDARIO DE DIAS INABILES
    // El alta se hace desde el formulario del propio index, así que tampoco hay 'create'.
    Route::resource('dias-inhabiles', DiaInhabilController::class)->except(['show', 'edit', 'update', 'create']);

    //RUTAS PARA IMPORTAR Y EXPORTAR BASE DE DATOS .SQL
    Route::get('/mantenimiento/respaldos', [BackupController::class, 'index'])->name('backup.index');
    Route::get('/mantenimiento/backup/exportar', [BackupController::class, 'exportar'])->name('backup.exportar');
    Route::post('/mantenimiento/backup/importar', [BackupController::class, 'importar'])->name('backup.importar');

    //PDF DE HORARIOS
    Route::post('/horarios/exportar-pdf', [HorarioController::class, 'exportPdf'])->name('horarios.exportPdf');
});


//=====================================================
// 5. ZONA ENCARGADO (Protegida con role:Encargado)
//=====================================================
Route::middleware(['auth', 'role:Encargado'])->prefix('encargado')->group(function () {

    // INICIO: panorama del día (su pantalla de entrada). Todo es de consulta.
    Route::get('inicio', [EncargadoController::class, 'inicio'])->name('encargado.inicio');
    // Las mismas vistas del admin, de solo consulta: la semana de clases y el
    // historial de uso libre (sin el botón de eliminar registros).
    Route::get('semana', [AdminDashboardController::class, 'semana'])->name('encargado.semana');
    Route::get('uso-libre', [AdminDashboardController::class, 'reporteUsoLibre'])->name('encargado.uso-libre');

    // GESTIÓN DE CLASES (la agenda del día)
    Route::get('dashboard', [EncargadoController::class, 'index'])->name('encargado.dashboard');
    // (Aquí había una ruta 'encargado.toggle-impartida' hacia un método que ya no
    // existe; ninguna pantalla la usaba y, si alguien la llamaba, daba error 500.)

    // GESTIÓN DE CLASES
    Route::get('clase/{id}/asistencia', [EncargadoController::class, 'verAsistenciaClase'])->name('encargado.ver-asistencia');
    Route::post('clase/{id}/estado', [EncargadoController::class, 'registrarEstadoClase'])->name('encargado.registrar-estado');
    Route::post('clase/{id}/liberar', [ClaseController::class, 'liberarClase'])->name('encargado.clase.liberar');

    // MANTENIMIENTO
    Route::get('incidencias', [IncidenciaController::class, 'index'])->name('encargado.incidencias.index');
    Route::put('incidencias/{id}/resolver', [IncidenciaController::class, 'resolver'])->name('encargado.incidencias.resolver');
    Route::get('/incidencias/historial', [IncidenciaController::class, 'historialCompleto'])->name('encargado.incidencias.historial');
});


//=====================================================
// 6. ZONA PROFESOR (Protegida con role:Profesor)
//=====================================================
Route::middleware(['auth', 'role:Profesor'])->prefix('profesor')->group(function () {

    Route::get('dashboard', [ProfesorController::class, 'index'])->name('profesor.dashboard');
    Route::get('/horario', [App\Http\Controllers\ProfesorController::class, 'horarioSemanal'])->name('profesor.horario');
    Route::get('historial', [ProfesorController::class, 'historial'])->name('profesor.historial');
    Route::get('revisar/{horario_id}', [ProfesorController::class, 'revisarClase'])->name('profesor.revisar-clase');
    Route::post('guardar-asistencia', [ProfesorController::class, 'guardarAsistencia'])->name('profesor.guardar-asistencia');
    Route::get('grupo/{horario_id}/reporte', [ProfesorController::class, 'verGrupo'])->name('profesor.ver-reporte');

    // Ruta principal de la vista de Reportes
    Route::get('/reportes', [App\Http\Controllers\ProfesorController::class, 'reportes'])->name('profesor.reportes');

    // Rutas de descarga
    Route::get('/reportes/lista-pdf/{horario_id}', [App\Http\Controllers\ProfesorController::class, 'descargarListaPdf'])->name('profesor.reportes.lista-pdf');
    Route::get('/reportes/lista-excel/{horario_id}', [App\Http\Controllers\ProfesorController::class, 'descargarListaExcel'])->name('profesor.reportes.lista-excel');
    Route::get('/reportes/estadisticas-pdf/{horario_id}', [App\Http\Controllers\ProfesorController::class, 'descargarEstadisticasPdf'])->name('profesor.reportes.estadisticas-pdf');
    Route::get('/reportes/estadisticas-excel/{horario_id}', [App\Http\Controllers\ProfesorController::class, 'descargarEstadisticasExcel'])->name('profesor.reportes.estadisticas-excel');
});


//=====================================================
// 7. ZONA ALUMNO (Protegida con role:Alumno)
//=====================================================
Route::middleware(['auth', 'role:Alumno'])->prefix('alumno')->group(function () {

    Route::get('dashboard', [AlumnoDashboardController::class, 'index'])->name('alumno.dashboard');
    Route::get('historial', [AlumnoDashboardController::class, 'historial'])->name('alumno.historial');
    Route::post('marcar-asistencia', [AlumnoDashboardController::class, 'marcarAsistencia'])->name('alumno.marcar-asistencia');
    Route::post('registrar-uso-libre', [AlumnoDashboardController::class, 'registrarUsoLibre'])->name('alumno.uso-libre');
    Route::post('terminar-uso-libre', [AlumnoDashboardController::class, 'terminarUsoLibre'])->name('alumno.terminar-uso-libre');
    Route::get('materias', [AlumnoDashboardController::class, 'misMaterias'])->name('alumno.materias');
    Route::get('materias/{materia}', [AlumnoDashboardController::class, 'detalleMateria'])->whereNumber('materia')->name('alumno.materias.detalle');
    Route::post('reportar-incidencia', [AlumnoDashboardController::class, 'reportarIncidencia'])->name('alumno.reportar-incidencia');
});
