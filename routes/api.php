<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

/*
|--------------------------------------------------------------------------
| Equipos de los centros de cómputo
|--------------------------------------------------------------------------
|
| Lo consulta el script que corre en cada computadora del laboratorio para
| saber si puede desbloquearse. Devuelve únicamente si la máquina tiene una
| sesión activa, sin ningún dato personal.
|
| Ejemplo:  /api/equipo/estado?centro=1&maquina=7
|
*/
Route::get('/equipo/estado', [App\Http\Controllers\Api\EquipoEstadoController::class, 'estado'])
    ->name('api.equipo.estado');
