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
        Schema::table('incidencias', function (Blueprint $table) {
            $table->text('solucion')->nullable()->after('descripcion'); // Qué se le hizo
            $table->dateTime('fecha_resolucion')->nullable()->after('updated_at'); // Cuándo quedó lista
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidencias', function (Blueprint $table) {
            //
        });
    }
};
