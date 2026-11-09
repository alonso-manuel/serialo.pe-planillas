<?php

use App\Enums\ClaveParametro;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CTS (semana 6):
 * - catálogo de entidades financieras donde se deposita la CTS (dato, no código);
 * - entidad de la cuenta CTS en la ficha del trabajador;
 * - liquidaciones de CTS por empresa y depósito (mayo / noviembre) con su detalle por trabajador;
 * - parámetros de CTS en REMYPE (si la instalación ya tiene parámetros; si no, los carga ParametroSeeder).
 */
return new class extends Migration
{
    /** ⚠ Lista inicial; se amplía como dato. */
    private const ENTIDADES = [
        ['BCP', 'Banco de Crédito del Perú'], ['BBVA', 'BBVA Perú'], ['INTERBANK', 'Interbank'],
        ['SCOTIABANK', 'Scotiabank Perú'], ['BANBIF', 'BanBif'], ['PICHINCHA', 'Banco Pichincha'],
        ['BANCOM', 'Banco de Comercio'], ['GNB', 'Banco GNB'], ['MIBANCO', 'Mibanco'],
        ['CMAC_AREQUIPA', 'Caja Arequipa'], ['CMAC_HUANCAYO', 'Caja Huancayo'], ['CMAC_PIURA', 'Caja Piura'],
        ['CMAC_CUSCO', 'Caja Cusco'], ['CMAC_TRUJILLO', 'Caja Trujillo'], ['CMAC_SULLANA', 'Caja Sullana'],
        ['CMAC_ICA', 'Caja Ica'], ['CMAC_TACNA', 'Caja Tacna'], ['CMAC_MAYNAS', 'Caja Maynas'],
        ['CMAC_SANTA', 'Caja del Santa'], ['CMAC_METROPOLITANA', 'Caja Metropolitana de Lima'],
        ['FIN_CONFIANZA', 'Financiera Confianza'], ['FIN_COMPARTAMOS', 'Compartamos Financiera'],
    ];

    public function up(): void
    {
        Schema::create('entidades_financieras', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
        $ahora = now();
        DB::table('entidades_financieras')->insert(array_map(
            fn (array $e) => ['codigo' => $e[0], 'nombre' => $e[1], 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            self::ENTIDADES,
        ));

        Schema::table('trabajadores', function (Blueprint $table) {
            $table->string('entidad_cts', 20)->nullable()->after('cuenta_cts');
        });

        Schema::create('liquidaciones_cts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes'); // 5 (mayo) | 11 (noviembre)
            $table->string('estado', 15)->default('borrador'); // borrador | cerrada
            $table->timestamp('calculado_en')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });

        Schema::create('liquidacion_cts_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_cts_id')->constrained('liquidaciones_cts')->cascadeOnDelete();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->date('computo_desde');
            $table->date('computo_hasta');
            $table->unsignedTinyInteger('meses');
            $table->unsignedTinyInteger('dias');
            $table->unsignedSmallInteger('dias_descontados')->default(0);
            $table->decimal('remuneracion_basica', 12, 2);
            $table->decimal('asignacion_familiar', 12, 2)->default(0);
            $table->decimal('sexto_gratificacion', 12, 2)->default(0);
            $table->decimal('promedio_variables', 12, 2)->default(0);
            $table->decimal('remuneracion_computable', 12, 2);
            $table->decimal('monto', 12, 2);
            $table->string('entidad_cts', 20)->nullable();
            $table->string('cuenta_cts', 30)->nullable();
            $table->json('avisos')->nullable();
            $table->timestamps();
            $table->unique(['liquidacion_cts_id', 'trabajador_id']);
        });

        if (DB::table('parametros')->exists()) {
            foreach ([[ClaveParametro::FactorCtsPequena, '0.50'], [ClaveParametro::FactorCtsMicro, '0.00']] as [$clave, $valor]) {
                if (! DB::table('parametros')->where('clave', $clave->value)->exists()) {
                    DB::table('parametros')->insert([
                        'clave' => $clave->value, 'valor' => $valor, 'vigente_desde' => '2013-07-01',
                        'base_legal' => 'Ley 30056 (D.S. 013-2013-PRODUCE) — VERIFICAR', 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidacion_cts_lineas');
        Schema::dropIfExists('liquidaciones_cts');
        Schema::table('trabajadores', fn (Blueprint $table) => $table->dropColumn('entidad_cts'));
        Schema::dropIfExists('entidades_financieras');
        DB::table('parametros')->whereIn('clave', ['factor_cts_pequena', 'factor_cts_micro'])->delete();
    }
};
