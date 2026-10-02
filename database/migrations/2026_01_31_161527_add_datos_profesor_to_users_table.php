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
        Schema::table('users', function (Blueprint $table) {
            // Agregamos apellidos (NULLABLE para no romper usuarios actuales)
            $table->string('apellido_paterno')->nullable()->after('name');
            $table->string('apellido_materno')->nullable()->after('apellido_paterno');

            // RFC: Único pero opcional (los alumnos a lo mejor no tienen)
            $table->string('rfc', 13)->nullable()->unique()->after('email');

            // Academia: Solo para profesores
            $table->string('academia')->nullable()->after('rol');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //
        });
    }
};
