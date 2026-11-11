<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * T-Registro (v1.1):
 * - datos del trabajador que exigen las estructuras 04 y 05 (tablas 9, 12, 24, 30 y 35 de SUNAT);
 * - catálogo de ocupaciones (tabla 30, sector privado), desde database/data/sunat/ocupaciones-tabla30.csv
 *   (extraído del Excel oficial de tablas paramétricas de SUNAT, versión 09/11/2026);
 * - código SUNAT del régimen pensionario de cada AFP (tabla 11).
 */
return new class extends Migration
{
    /** Tabla 11: régimen pensionario. */
    private const AFP_SUNAT = ['INTEGRA' => '21', 'HORIZONTE' => '22', 'PROFUTURO' => '23', 'PRIMA' => '24', 'HABITAT' => '25'];

    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('categoria_ocupacional', 2)->nullable()->after('cargo');   // tabla 24
            $table->string('ocupacion', 6)->nullable()->after('categoria_ocupacional'); // tabla 30
            $table->string('situacion_educativa', 2)->nullable()->after('ocupacion'); // tabla 9
            $table->string('modalidad_contrato', 2)->nullable()->after('tipo_contrato'); // tabla 12 (plazo fijo)
            $table->string('situacion_especial', 1)->default('0')->after('modalidad_contrato'); // tabla 35
            $table->boolean('discapacidad')->default(false)->after('situacion_especial');
            $table->boolean('sindicalizado')->default(false)->after('discapacidad');
            $table->string('nacionalidad', 4)->nullable()->after('sexo');            // tabla 4 (extranjeros)
            $table->string('pais_emisor', 3)->nullable()->after('nacionalidad');     // tabla 26 (pasaporte)
        });

        Schema::create('ocupaciones_sunat', function (Blueprint $table) {
            $table->string('codigo', 6)->primary();
            $table->string('nombre');
            $table->boolean('ejecutivo');
            $table->boolean('empleado');
            $table->boolean('obrero');
        });

        $csv = fopen(database_path('data/sunat/ocupaciones-tabla30.csv'), 'r');
        if ($csv !== false) {
            fgetcsv($csv, null, ',', '"', ''); // encabezado
            $lote = [];
            while (($f = fgetcsv($csv, null, ',', '"', '')) !== false) {
                $lote[] = ['codigo' => $f[0], 'nombre' => $f[1], 'ejecutivo' => $f[2] === '1', 'empleado' => $f[3] === '1', 'obrero' => $f[4] === '1'];
                if (count($lote) === 500) {
                    DB::table('ocupaciones_sunat')->insert($lote);
                    $lote = [];
                }
            }
            if ($lote !== []) {
                DB::table('ocupaciones_sunat')->insert($lote);
            }
            fclose($csv);
        }

        foreach (self::AFP_SUNAT as $codigo => $sunat) {
            DB::table('afps')->where('codigo', $codigo)->update(['codigo_sunat' => $sunat]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ocupaciones_sunat');

        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn([
                'categoria_ocupacional', 'ocupacion', 'situacion_educativa', 'modalidad_contrato', 'situacion_especial',
                'discapacidad', 'sindicalizado', 'nacionalidad', 'pais_emisor',
            ]);
        });
    }
};
