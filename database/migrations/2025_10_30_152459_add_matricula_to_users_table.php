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
        Schema::table('users', function (Blueprint $table) {
            // Añadimos matricula, puede ser null (para no alumnos)
            // Debe ser única (si no es null)
            // La ponemos después de 'rol'
            $table->string('matricula')->nullable()->unique()->after('rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Importante: Para poder borrar una columna unique,
            // primero hay que borrar el índice unique.
            // El nombre del índice lo crea Laravel: table_column_unique
            $table->dropUnique('users_matricula_unique');
            $table->dropColumn('matricula');
        });
    }
};
