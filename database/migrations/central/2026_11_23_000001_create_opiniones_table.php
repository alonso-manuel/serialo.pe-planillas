<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Base CENTRAL: comentarios que los usuarios de cada cuenta envían desde su estudio (qué les parece el sistema,
 * qué falla, qué falta). Se leen en el panel central. Se guarda una copia del nombre de la cuenta y del usuario
 * porque el comentario se conserva aunque la cuenta se elimine.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opiniones', function (Blueprint $table) {
            $table->id();
            $table->string('estudio_id')->nullable()->index();
            $table->foreign('estudio_id')->references('id')->on('estudios')->nullOnDelete()->cascadeOnUpdate();
            $table->string('estudio_nombre');
            $table->unsignedBigInteger('usuario_id');
            $table->string('usuario_nombre');
            $table->string('usuario_correo');
            $table->string('usuario_rol', 20);
            $table->unsignedTinyInteger('calificacion')->nullable(); // 1 a 5; opcional
            $table->text('comentario');
            $table->timestamp('leida_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opiniones');
    }
};
