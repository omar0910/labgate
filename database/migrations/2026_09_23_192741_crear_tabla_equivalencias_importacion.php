<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que el sistema aprende del archivo de horarios del plantel.
 *
 * En ese archivo las cosas no vienen con el nombre del sistema: los laboratorios
 * son claves ("CI", "CE"), los docentes también ("EMEZA", "JCASIMIRO") y las
 * materias vienen abreviadas ("INTROD. A PROGRAMACIÓN"). La primera vez se
 * confirma a mano a qué corresponde cada una, y aquí queda guardado para que en
 * las siguientes importaciones se resuelva solo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equivalencias_importacion', function (Blueprint $table) {
            $table->id();

            // 'area', 'docente' o 'materia'.
            $table->string('tipo', 20);

            // El texto del archivo, ya normalizado (mayúsculas, sin acentos ni puntos).
            $table->string('clave', 255);

            // Tal como venía escrito, para poder mostrarlo en pantalla.
            $table->string('texto', 255)->nullable();

            // A qué registro del sistema corresponde (centro, usuario o materia).
            $table->unsignedBigInteger('destino_id');

            $table->timestamps();

            $table->unique(['tipo', 'clave']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equivalencias_importacion');
    }
};
