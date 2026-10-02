<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asistencias', function (Blueprint $table) {

            // 1. Verificamos si NO existe 'estado' antes de crearla
            if (!Schema::hasColumn('asistencias', 'estado')) {
                $table->enum('estado', ['presente', 'falta', 'justificado'])
                    ->default('presente')
                    ->after('fecha');
            }

            // 2. Verificamos si NO existe 'comentario' antes de crearla
            if (!Schema::hasColumn('asistencias', 'comentario')) {
                $table->text('comentario')
                    ->nullable()
                    ->after('estado');
            }
        });
    }

    public function down()
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn(['estado', 'comentario']);
        });
    }
};
