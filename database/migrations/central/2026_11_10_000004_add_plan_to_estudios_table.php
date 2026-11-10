<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL: plan de cada cuenta del modo SaaS (reemplaza a la licencia firmada de una instalación única).
 * Límites null = ilimitado. `aviso_vencimiento` evita repetir el correo de «por vencer» o «vencida».
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->string('plan', 40)->default('Estudio')->after('tipo');
            $table->unsignedInteger('max_empresas')->nullable()->after('plan');
            $table->unsignedInteger('max_usuarios')->nullable()->after('max_empresas');
            $table->unsignedInteger('max_trabajadores')->nullable()->after('max_usuarios');
            $table->string('aviso_vencimiento', 20)->nullable()->after('suspendido_en'); // por_vencer | vencida
        });
    }

    public function down(): void
    {
        Schema::table('estudios', fn (Blueprint $table) => $table->dropColumn(['plan', 'max_empresas', 'max_usuarios', 'max_trabajadores', 'aviso_vencimiento']));
    }
};
