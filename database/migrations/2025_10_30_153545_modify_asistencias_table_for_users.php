<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // 1. Cambiar el nombre de la columna de alumno_id a user_id
            $table->renameColumn('alumno_id', 'user_id');

            // 2. Hacer horario_id opcional (nullable)
            // Usamos change() para modificar una columna existente
            $table->unsignedBigInteger('horario_id')->nullable()->change();

            // 3. Añadir la nueva llave foránea a la tabla 'users'
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // 4. Añadir nuevas columnas para el "Uso Libre"

            // Para saber si es 'Clase' o 'Uso Libre'
            $table->string('tipo')->default('Clase')->after('estado');

            // Para saber cuándo se registró (auto-asistencia del alumno)
            $table->timestamp('fecha_hora_registro')->nullable()->after('tipo');

            // Para saber en qué lab fue el "Uso Libre"
            $table->foreignId('centro_computo_id')->nullable()->after('tipo')
                ->constrained('centro_computos')->onDelete('set null');

            // 5. Eliminar la columna 'fecha' (ya no es necesaria si tenemos 'fecha_hora_registro')
            // $table->dropColumn('fecha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // 1. Eliminar la nueva llave foránea
            $table->dropForeign(['user_id']);

            // 2. Renombrar 'user_id' de vuelta a 'alumno_id'
            $table->renameColumn('user_id', 'alumno_id');

            // 3. Volver a hacer 'horario_id' no nullable
            $table->unsignedBigInteger('horario_id')->nullable(false)->change();

            // 4. Eliminar las nuevas columnas
            $table->dropForeign(['centro_computo_id']);
            $table->dropColumn(['tipo', 'fecha_hora_registro', 'centro_computo_id']);
        });
    }
};
