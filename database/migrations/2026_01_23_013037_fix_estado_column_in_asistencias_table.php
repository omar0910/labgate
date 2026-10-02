<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('asistencias', function (Blueprint $table) {
            // 1. Si existe la columna 'estado' (la vieja que es integer), la borramos
            if (Schema::hasColumn('asistencias', 'estado')) {
                $table->dropColumn('estado');
            }
        });

        // 2. Volvemos a crearla, ahora sí como ENUM (texto)
        Schema::table('asistencias', function (Blueprint $table) {
            $table->enum('estado', ['presente', 'falta', 'justificado'])
                ->default('presente')
                ->after('fecha');
        });
    }

    public function down()
    {
        // En caso de revertir, borramos la columna nueva
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }
};
