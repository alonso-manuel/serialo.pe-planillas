<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Régimen laboral por trabajador: en una micro o pequeña empresa inscrita en el REMYPE, los trabajadores contratados
 * antes de la inscripción conservan el régimen general (TUO de la Ley MYPE, D.S. 013-2013-PRODUCE) ⚠.
 * null = el régimen de la empresa (lo normal); «general» = excepción dentro de una MYPE.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('regimen_laboral', 20)->nullable()->after('tipo_contrato');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn('regimen_laboral');
        });
    }
};
