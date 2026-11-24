<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL: solicitudes de cuenta de prueba enviadas desde la web pública (sitio/, formulario «Pide tu cuenta
 * de prueba»). Se atienden desde el panel central. Datos personales de prospectos (Ley 29733): solo los del formulario.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('tipo', 10); // estudio | empresa
            $table->string('organizacion', 150);
            $table->string('celular', 20);
            $table->string('correo', 150);
            $table->string('empresas', 60)->nullable();
            $table->text('mensaje')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('atendida_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitudes');
    }
};
