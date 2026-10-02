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
            // Añadimos 'username'
            // - nullable() -> Porque los alumnos usarán 'matricula'
            // - unique() -> Para que los profes/admins no se repitan
            // - after('rol') -> Para orden en la base de datos
            $table->string('username')->nullable()->unique()->after('rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Para borrar una columna unique, primero se borra el índice
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
