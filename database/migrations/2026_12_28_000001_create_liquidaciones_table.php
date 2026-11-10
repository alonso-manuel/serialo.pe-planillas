<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Liquidación de beneficios sociales al cese (una por trabajador y cese). Cerrada = inmutable.
 * Agrega los conceptos de la tabla 22 que paga (⚠ verificar códigos contra la tabla oficial vigente).
 */
return new class extends Migration
{
    /** código => [nombre, essalud, onp, afp, quinta, cuenta de cargo] */
    public const CONCEPTOS = [
        '0407' => ['Gratificación proporcional (trunca) – Ley 29351', false, false, false, true, '6214'],
        '0114' => ['Vacaciones truncas', true, true, true, true, '6215'],
        '0911' => ['Indemnización vacacional (vacaciones no gozadas)', false, false, false, false, '6291'],
    ];

    public function up(): void
    {
        Schema::create('liquidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('trabajador_id')->constrained('trabajadores')->cascadeOnDelete();
            $table->date('fecha_cese');
            $table->string('estado', 15)->default('borrador'); // borrador | cerrada
            $table->decimal('gratificacion_pendiente', 12, 2)->default(0);
            $table->decimal('bonificacion_pendiente', 12, 2)->default(0);
            $table->decimal('gratificacion_trunca', 12, 2)->default(0);
            $table->decimal('bonificacion_trunca', 12, 2)->default(0);
            $table->decimal('cts', 12, 2)->default(0);
            $table->decimal('vacaciones_no_gozadas', 12, 2)->default(0);
            $table->decimal('indemnizacion_vacacional', 12, 2)->default(0);
            $table->decimal('vacaciones_truncas', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->json('detalle')->nullable(); // meses, días y remuneraciones computables de cada beneficio
            $table->json('avisos')->nullable();
            $table->timestamp('calculado_en')->nullable();
            $table->foreignId('cerrado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cerrado_en')->nullable();
            $table->timestamps();
            $table->unique(['trabajador_id', 'fecha_cese']);
        });

        $ahora = now();
        foreach (self::CONCEPTOS as $codigo => [$nombre, $essalud, $onp, $afp, $quinta, $cargo]) {
            if (DB::table('conceptos')->where('codigo_plame', $codigo)->exists()) {
                continue;
            }
            DB::table('conceptos')->insert([
                'codigo_plame' => $codigo, 'nombre' => $nombre, 'tipo' => 'ingreso',
                'afecto_essalud' => $essalud, 'afecto_onp' => $onp, 'afecto_afp' => $afp, 'afecto_quinta' => $quinta,
                'cuenta_cargo' => $cargo, 'sistema' => true, 'activo' => true,
                'created_at' => $ahora, 'updated_at' => $ahora,
            ]);
        }

        // La bonificación proporcional ahora la usa el motor.
        DB::table('conceptos')->whereIn('codigo_plame', ['0313', '0904'])->update(['sistema' => true]);
    }

    public function down(): void
    {
        Schema::dropIfExists('liquidaciones');
    }
};
