<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL: actividad de cada cuenta para el panel (¿el prospecto entró a probar la demo?).
 * Se actualiza en cada ingreso de un usuario del estudio (RegistrarIngresoEstudio); el acceso de soporte no cuenta.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->unsignedInteger('ingresos')->default(0)->after('aviso_vencimiento');
            $table->timestamp('ultimo_ingreso_en')->nullable()->after('ingresos');
            $table->string('ultimo_ingreso_usuario')->nullable()->after('ultimo_ingreso_en');
        });
    }

    public function down(): void
    {
        Schema::table('estudios', fn (Blueprint $table) => $table->dropColumn(['ingresos', 'ultimo_ingreso_en', 'ultimo_ingreso_usuario']));
    }
};
