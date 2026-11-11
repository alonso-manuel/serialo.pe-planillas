<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Banco o caja de la cuenta de sueldo (catálogo entidades_financieras), para el archivo de pagos de la planilla. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('entidad_sueldo', 20)->nullable()->after('cuenta_sueldo');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', fn (Blueprint $table) => $table->dropColumn('entidad_sueldo'));
    }
};
