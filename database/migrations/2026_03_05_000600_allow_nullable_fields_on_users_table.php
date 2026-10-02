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
            // Esto le quita el candado de "Obligatorio" en la base de datos
            $table->string('apellido_paterno')->nullable()->change();
            $table->string('apellido_materno')->nullable()->change();
            $table->string('email')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // En caso de querer volver atrás
            $table->string('apellido_paterno')->nullable(false)->change();
            $table->string('apellido_materno')->nullable(false)->change();
            $table->string('email')->nullable(false)->change();
        });
    }
};
