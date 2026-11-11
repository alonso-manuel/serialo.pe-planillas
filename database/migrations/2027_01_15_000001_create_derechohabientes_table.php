<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Derechohabientes del trabajador (EsSalud / T-Registro): estructuras 13 (alta) y 24 (baja) de la R.M. 121-2011-TR,
 * tablas 19 (vínculo), 20 (motivo de baja) y 27 (documento que sustenta el vínculo).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('derechohabientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->string('tipo_documento', 2);
            $table->string('numero_documento', 15);
            $table->string('pais_emisor', 3)->nullable();
            $table->date('fecha_nacimiento');
            $table->string('apellido_paterno', 40);
            $table->string('apellido_materno', 40)->nullable();
            $table->string('nombres', 40);
            $table->char('sexo', 1);                          // M / F
            $table->string('vinculo', 2);                     // tabla 19
            $table->string('documento_vinculo', 2)->nullable(); // tabla 27
            $table->string('numero_documento_vinculo', 20)->nullable();
            $table->string('mes_concepcion', 7)->nullable();  // AAAA-MM, solo gestante
            $table->date('fecha_alta');
            $table->date('fecha_baja')->nullable();
            $table->string('motivo_baja', 2)->nullable();     // tabla 20
            $table->timestamps();
            $table->unique(['trabajador_id', 'tipo_documento', 'numero_documento'], 'derechohabientes_documento_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('derechohabientes');
    }
};
