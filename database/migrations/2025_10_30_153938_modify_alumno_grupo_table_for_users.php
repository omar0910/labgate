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
        Schema::table('alumno_grupo', function (Blueprint $table) {
            // 1. Eliminar la llave foránea de 'grupo_id' temporalmente
            // (La de 'alumno_id' ya la borramos en el Paso 2)
            $table->dropForeign('alumno_grupo_grupo_id_foreign');

            // 2. Eliminar el índice unique (esta era la línea que fallaba)
            $table->dropUnique('alumno_grupo_alumno_id_grupo_id_unique');

            // 3. Renombrar la columna
            $table->renameColumn('alumno_id', 'user_id');

            // 4. Añadir la nueva llave foránea a la tabla 'users'
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // 5. Volver a añadir la llave foránea de 'grupo_id'
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');

            // 6. Crear el nuevo índice unique con la columna renombrada
            $table->unique(['user_id', 'grupo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('alumno_grupo', function (Blueprint $table) {
            // 1. Borrar todo lo nuevo
            $table->dropForeign(['user_id']);
            $table->dropForeign(['grupo_id']);
            $table->dropUnique('alumno_grupo_user_id_grupo_id_unique');

            // 2. Renombrar la columna de vuelta
            $table->renameColumn('user_id', 'alumno_id');

            // 3. Recrear las llaves foráneas originales
            $table->foreign('alumno_id')->references('id')->on('alumnos')->onDelete('cascade');
            $table->foreign('grupo_id')->references('id')->on('grupos')->onDelete('cascade');
            $table->unique(['alumno_id', 'grupo_id']);
        });
    }
};
