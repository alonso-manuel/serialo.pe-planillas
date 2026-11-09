<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Logo de la empresa (PNG reducido, en almacenamiento privado) para boletas, constancias y reportes. */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('logo_path')->nullable()->after('representante_legal');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('logo_path'));
    }
};
