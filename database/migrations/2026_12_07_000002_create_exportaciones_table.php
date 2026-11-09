<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historial de archivos oficiales generados (PLAME, AFPnet…): qué se exportó, cuándo, quién
     * y el hash SHA-256 de cada archivo, para saber exactamente qué se declaró.
     */
    public function up(): void
    {
        Schema::create('exportaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // plame | afpnet
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('periodo_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->json('archivos'); // [{nombre, sha256, bytes}]
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->index(['empresa_id', 'tipo', 'anio', 'mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exportaciones');
    }
};
