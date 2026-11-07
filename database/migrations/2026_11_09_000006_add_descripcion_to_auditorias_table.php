<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            // "Qué era" el registro al momento del cambio (sigue legible si luego se elimina).
            $table->string('descripcion')->nullable()->after('auditable_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('auditorias', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropColumn('descripcion');
        });
    }
};
