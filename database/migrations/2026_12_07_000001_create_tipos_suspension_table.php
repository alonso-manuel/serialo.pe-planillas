<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla 21 de SUNAT (tipos de suspensión de la relación laboral), usada en el archivo .snl del PLAME.
 * Es un catálogo: se carga aquí mismo para que también llegue a las instalaciones existentes.
 * ⚠ VERIFICAR códigos y descripciones contra la tabla 21 vigente antes de producción.
 * `uso`: 'falta' = código de las faltas de la grilla; 'subsidio' = tipos elegibles para los días subsidiados.
 */
return new class extends Migration
{
    private const FILAS = [
        ['01', 'S.P. Sanción disciplinaria', null],
        ['02', 'S.P. Ejercicio del derecho de huelga', null],
        ['03', 'S.P. Detención del trabajador, salvo el caso de condena privativa de la libertad', null],
        ['04', 'S.P. Inhabilitación administrativa o judicial por periodo no superior a tres meses', null],
        ['05', 'S.P. Permiso, licencia u otros motivos sin goce de haber', null],
        ['06', 'S.P. Caso fortuito o fuerza mayor', null],
        ['07', 'S.P. Falta no justificada', 'falta'],
        ['08', 'S.P. Por temporada o intermitente', null],
        ['20', 'S.I. Enfermedad o accidente (primeros veinte días)', null],
        ['21', 'S.I. Incapacidad temporal (invalidez, enfermedad y accidentes)', 'subsidio'],
        ['22', 'S.I. Maternidad durante el descanso pre y post natal', 'subsidio'],
        ['23', 'S.I. Descanso vacacional', null],
        ['24', 'S.I. Licencia para desempeñar cargo cívico y para cumplir con el servicio militar obligatorio', null],
        ['25', 'S.I. Permiso y licencia para el desempeño de cargos sindicales', null],
        ['26', 'S.I. Licencia con goce de haber', null],
        ['27', 'S.I. Días compensados por horas trabajadas en sobretiempo', null],
    ];

    public function up(): void
    {
        Schema::create('tipos_suspension', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 2)->unique();
            $table->string('descripcion');
            $table->string('uso', 10)->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        $ahora = now();
        DB::table('tipos_suspension')->insert(array_map(fn (array $f) => [
            'codigo' => $f[0], 'descripcion' => $f[1], 'uso' => $f[2], 'activo' => true,
            'created_at' => $ahora, 'updated_at' => $ahora,
        ], self::FILAS));

        Schema::table('planilla_lineas', function (Blueprint $table) {
            // Tipo (tabla 21) de los días subsidiados: 21 incapacidad temporal, 22 maternidad.
            $table->string('tipo_subsidio', 2)->nullable()->after('dias_subsidiados');
        });

        DB::table('planilla_lineas')->where('dias_subsidiados', '>', 0)->update(['tipo_subsidio' => '21']);
    }

    public function down(): void
    {
        Schema::table('planilla_lineas', fn (Blueprint $table) => $table->dropColumn('tipo_subsidio'));
        Schema::dropIfExists('tipos_suspension');
    }
};
