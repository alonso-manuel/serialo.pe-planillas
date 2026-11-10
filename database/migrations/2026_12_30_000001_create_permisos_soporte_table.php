<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Acceso de soporte (modo SaaS): el administrador del estudio lo autoriza por un tiempo y Serialo.pe entra
 * desde el panel central con un usuario propio («Soporte Serialo.pe») que no cuenta para el plan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('es_soporte')->default(false)->after('activo');
        });

        Schema::create('permisos_soporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('autorizado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('vence_en');
            $table->dateTime('revocado_en')->nullable();
            // Enlace de ingreso de un solo uso (hash SHA-256) generado por el panel; vale un minuto.
            $table->string('token_hash', 64)->nullable()->index();
            $table->dateTime('token_vence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('permisos_soporte');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('es_soporte');
        });
    }
};
