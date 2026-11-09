<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cuentas del Plan Contable General Empresarial para la centralización de la planilla.
 * - Ingresos: cuenta de cargo (gasto).
 * - Aportes y descuentos del trabajador: cuenta de abono (lo que se debe pagar o se recupera).
 * - Aportes del empleador: cargo (gasto) y abono (tributo por pagar).
 * El neto va a la cuenta de remuneraciones por pagar (config planillas.cuenta_remuneraciones_por_pagar).
 * ⚠ Propuesta inicial: cada estudio la ajusta en Conceptos.
 */
return new class extends Migration
{
    public const CUENTAS = [
        // código => [cargo, abono]
        '0121' => ['6211', null], '0105' => ['6211', null], '0106' => ['6211', null],
        '0118' => ['6215', null], '0201' => ['6221', null],
        '0406' => ['6214', null], '0312' => ['6214', null], '0313' => ['6214', null],
        '0904' => ['6291', null], '0909' => ['625', null],
        '0601' => [null, '4170'], '0606' => [null, '4170'], '0608' => [null, '4170'],
        '0607' => [null, '4032'], '0605' => [null, '40173'],
        '0701' => [null, '1411'],
        '0804' => ['6271', '4031'], '0811' => ['6271', '4039'],
    ];

    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->string('cuenta_cargo', 10)->nullable()->after('afecto_quinta');
            $table->string('cuenta_abono', 10)->nullable()->after('cuenta_cargo');
        });

        foreach (self::CUENTAS as $codigo => [$cargo, $abono]) {
            DB::table('conceptos')->where('codigo_plame', $codigo)->update(['cuenta_cargo' => $cargo, 'cuenta_abono' => $abono]);
        }
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropColumn(['cuenta_cargo', 'cuenta_abono']);
        });
    }
};
