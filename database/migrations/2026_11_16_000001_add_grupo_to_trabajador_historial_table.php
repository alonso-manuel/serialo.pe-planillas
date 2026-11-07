<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajador_historial', function (Blueprint $table) {
            // Agrupa los campos que cambian juntos (p. ej. pensión: sistema + AFP + comisión + CUSPP).
            $table->string('grupo', 30)->default('')->after('trabajador_id');
            $table->index(['trabajador_id', 'grupo', 'vigente_desde']);
        });
    }

    public function down(): void
    {
        Schema::table('trabajador_historial', function (Blueprint $table) {
            $table->dropIndex(['trabajador_id', 'grupo', 'vigente_desde']);
            $table->dropColumn('grupo');
        });
    }
};
