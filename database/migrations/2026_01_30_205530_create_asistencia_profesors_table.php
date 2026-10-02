<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('asistencia_profesores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->constrained()->onDelete('cascade');
            $table->date('fecha');

            // Estados:
            // 'asistio': Todo normal.
            // 'falta': No vino (Se borran asistencias de alumnos).
            // 'justificado': Permiso (Se borran asistencias de alumnos).
            // 'retardo': Llegó tarde (No afecta alumnos).
            $table->enum('estado', ['asistio', 'falta', 'justificado', 'retardo'])->default('asistio');

            $table->text('observaciones')->nullable();
            $table->timestamps();

            // Evitar duplicados por día y horario
            $table->unique(['horario_id', 'fecha']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencia_profesors');
    }
};
