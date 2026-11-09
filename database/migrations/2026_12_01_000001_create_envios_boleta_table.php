<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Constancia de envío de boletas por correo (D.S. 009-2011-TR): un registro por intento de envío. */
    public function up(): void
    {
        Schema::create('envios_boleta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('planilla_linea_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('estado', 15)->default('pendiente'); // pendiente | enviado | fallido
            $table->text('error')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['planilla_linea_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('envios_boleta');
    }
};
