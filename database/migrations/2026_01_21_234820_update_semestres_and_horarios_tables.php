<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Modificar tabla SEMESTRES
        Schema::table('semestres', function (Blueprint $table) {
            // Agregamos fechas para saber cuándo inicia y termina el ciclo
            // 'nullable' por si ya tienes datos, no truene al actualizar
            $table->date('fecha_inicio')->nullable()->after('nombre');
            $table->date('fecha_fin')->nullable()->after('fecha_inicio');

            // El switch maestro: solo uno estará en TRUE
            $table->boolean('es_activo')->default(false)->after('fecha_fin');
        });

        // 2. Modificar tabla HORARIOS
        Schema::table('horarios', function (Blueprint $table) {
            // Campo para reservas especiales (una sola fecha)
            // Si es NULL, se asume que es clase recurrente semestral
            $table->date('fecha_especial')->nullable()->after('dia_semana');

            // Opcional: Para describir el evento si no es una clase normal
            $table->string('comentario')->nullable()->after('fecha_especial');
        });
    }

    public function down()
    {
        // Esto es por si queremos deshacer los cambios
        Schema::table('semestres', function (Blueprint $table) {
            $table->dropColumn(['fecha_inicio', 'fecha_fin', 'es_activo']);
        });

        Schema::table('horarios', function (Blueprint $table) {
            $table->dropColumn(['fecha_especial', 'comentario']);
        });
    }
};
