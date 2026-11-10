<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL: panel de Serialo.pe (admin.serialo.pe). El superadmin no es usuario de ningún estudio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('estudios', function (Blueprint $table) {
            $table->string('tipo', 20)->default('cliente')->after('nombre');     // demo | cliente
            $table->string('estado', 20)->default('activo')->after('tipo');      // activo | suspendido
            $table->date('vence_en')->nullable()->after('estado');
            $table->string('correo_admin')->nullable()->after('vence_en');
            $table->timestamp('suspendido_en')->nullable()->after('correo_admin');
        });

        Schema::create('superadmins', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        // Sesiones del panel (las de cada estudio están en su propia base).
        Schema::create('sesiones_panel', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Lo que hace el superadmin (crear, suspender, eliminar cuentas…). Inmutable.
        Schema::create('auditorias_centrales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('superadmin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('estudio_id', 30)->nullable()->index(); // sin FK: queda aunque se elimine el estudio
            $table->string('accion', 30);
            $table->string('descripcion');
            $table->json('cambios')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditorias_centrales');
        Schema::dropIfExists('sesiones_panel');
        Schema::dropIfExists('superadmins');
        Schema::table('estudios', fn (Blueprint $table) => $table->dropColumn(['tipo', 'estado', 'vence_en', 'correo_admin', 'suspendido_en']));
    }
};
