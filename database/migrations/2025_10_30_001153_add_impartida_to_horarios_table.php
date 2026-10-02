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
        Schema::table('horarios', function (Blueprint $table) {
            // Añadimos la nueva columna 'impartida'
            // Es booleana (true/false)
            // Por defecto, estará en 'false' (no impartida)
            // La ponemos después de 'hora_fin' para orden
            $table->boolean('impartida')->default(false)->after('hora_fin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            // Esto permite deshacer la migración si es necesario
            $table->dropColumn('impartida');
        });
    }
};
