<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Firma digital (certificado .pfx/.p12 del representante legal) para firmar en PDF las boletas
 * y los documentos laborales de cada empresa (D. Leg. 1310). Certificado y clave se guardan cifrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firmas_digitales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('titular');
            $table->string('documento')->nullable();     // serialNumber del certificado (DNI o RUC)
            $table->string('emisor');
            $table->string('numero_serie', 100);
            $table->string('huella', 64);                 // SHA-256 del certificado
            $table->dateTime('valido_desde');
            $table->dateTime('valido_hasta');
            $table->text('certificado');                  // .p12 cifrado con APP_KEY
            $table->text('clave');                        // su contraseña, cifrada
            $table->foreignId('registrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('firmas_digitales');
    }
};
