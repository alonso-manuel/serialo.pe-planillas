<?php

namespace Database\Seeders;

use App\Enums\TipoConcepto as T;
use App\Models\Concepto;
use Illuminate\Database\Seeder;

/**
 * Conceptos remunerativos (tabla 22 de SUNAT).
 * ⚠ VERIFICAR cada código y su afectación contra la tabla 22 oficial vigente antes de producción.
 * "sistema" = lo usa el motor de cálculo; no se puede desactivar ni borrar.
 */
class ConceptoSeeder extends Seeder
{
    /**
     * Cuentas del PCGE para la centralización: código => [cargo, abono]. ⚠ Propuesta: cada estudio la ajusta.
     * (La misma lista carga la migración add_cuentas_contables_to_conceptos en instalaciones existentes.)
     */
    public const CUENTAS = [
        '0121' => ['6211', null], '0105' => ['6211', null], '0106' => ['6211', null],
        '0118' => ['6215', null], '0201' => ['6221', null],
        '0406' => ['6214', null], '0312' => ['6214', null], '0313' => ['6214', null],
        '0904' => ['6291', null], '0909' => ['625', null],
        '0407' => ['6214', null], '0114' => ['6215', null], '0911' => ['6291', null],
        '0601' => [null, '4170'], '0606' => [null, '4170'], '0608' => [null, '4170'],
        '0607' => [null, '4032'], '0605' => [null, '40173'],
        '0701' => [null, '1411'],
        '0804' => ['6271', '4031'], '0811' => ['6271', '4039'],
    ];

    public function run(): void
    {
        //  código, nombre, tipo, essalud, onp, afp, quinta, sistema
        $filas = [
            ['0121', 'Remuneración o jornal básico', T::Ingreso, 1, 1, 1, 1, 1],
            ['0118', 'Remuneración vacacional', T::Ingreso, 1, 1, 1, 1, 1],
            ['0105', 'Trabajo en sobretiempo (horas extras) 25 %', T::Ingreso, 1, 1, 1, 1, 1],
            ['0106', 'Trabajo en sobretiempo (horas extras) 35 %', T::Ingreso, 1, 1, 1, 1, 1],
            ['0201', 'Asignación familiar', T::Ingreso, 1, 1, 1, 1, 1],
            ['0312', 'Bonificación extraordinaria Ley 29351 (gratificación)', T::Ingreso, 0, 0, 0, 1, 1],
            ['0313', 'Bonificación extraordinaria proporcional Ley 29351', T::Ingreso, 0, 0, 0, 1, 1],
            ['0406', 'Gratificaciones de fiestas patrias y navidad – Ley 29351', T::Ingreso, 0, 0, 0, 1, 1],
            ['0407', 'Gratificación proporcional (trunca) – Ley 29351', T::Ingreso, 0, 0, 0, 1, 1],
            ['0114', 'Vacaciones truncas', T::Ingreso, 1, 1, 1, 1, 1],
            ['0904', 'Compensación por tiempo de servicios (CTS)', T::Ingreso, 0, 0, 0, 0, 1],
            ['0911', 'Indemnización vacacional (vacaciones no gozadas)', T::Ingreso, 0, 0, 0, 0, 1],
            ['0909', 'Movilidad supeditada a asistencia', T::Ingreso, 0, 0, 0, 0, 0],
            ['0601', 'Comisión AFP porcentual', T::AporteTrabajador, 0, 0, 0, 0, 1],
            ['0605', 'Renta quinta categoría retenciones', T::DescuentoTrabajador, 0, 0, 0, 0, 0],
            ['0606', 'Prima de seguro AFP', T::AporteTrabajador, 0, 0, 0, 0, 1],
            ['0607', 'Sistema Nacional de Pensiones – D.L. 19990', T::AporteTrabajador, 0, 0, 0, 0, 1],
            ['0608', 'SPP – aportación obligatoria', T::AporteTrabajador, 0, 0, 0, 0, 1],
            ['0701', 'Adelanto', T::DescuentoTrabajador, 0, 0, 0, 0, 0],
            ['0804', 'EsSalud – seguro regular trabajador', T::AporteEmpleador, 0, 0, 0, 0, 1],
            ['0811', 'SIS – microempresa', T::AporteEmpleador, 0, 0, 0, 0, 1],
        ];

        foreach ($filas as [$codigo, $nombre, $tipo, $essalud, $onp, $afp, $quinta, $sistema]) {
            Concepto::updateOrCreate(['codigo_plame' => $codigo], [
                'nombre' => $nombre,
                'tipo' => $tipo,
                'afecto_essalud' => (bool) $essalud,
                'afecto_onp' => (bool) $onp,
                'afecto_afp' => (bool) $afp,
                'afecto_quinta' => (bool) $quinta,
                'cuenta_cargo' => self::CUENTAS[$codigo][0] ?? null,
                'cuenta_abono' => self::CUENTAS[$codigo][1] ?? null,
                'sistema' => (bool) $sistema,
                'activo' => true,
            ]);
        }
    }
}
