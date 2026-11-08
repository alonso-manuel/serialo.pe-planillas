<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/**
 * Días laborados que se proponen al abrir un mes, en MES COMERCIAL DE 30 DÍAS.
 * ⚠ Criterio a confirmar con el contador:
 * - Mes completo = 30 (incluso febrero o meses de 31).
 * - Ingreso en el mes: desde ese día hasta el 30 (ingreso el 16 → 15 días; el 31 → 1 día).
 * - Cese en el mes: hasta ese día (cese el 10 → 10 días); cese el último día del mes = 30.
 * Las faltas, licencias y subsidios se ajustan después en las novedades.
 */
final class DiasLaborados
{
    public const DIAS_MES = 30;

    public static function proponer(DateTimeImmutable $ingreso, ?DateTimeImmutable $cese, int $anio, int $mes): int
    {
        $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01', $anio, $mes));
        $ultimoDia = $primerDia->modify('last day of this month');

        if ($ingreso > $ultimoDia || ($cese !== null && $cese < $primerDia)) {
            return 0;
        }

        $desde = $ingreso >= $primerDia ? min((int) $ingreso->format('j'), self::DIAS_MES) : 1;

        $hasta = self::DIAS_MES;
        if ($cese !== null && $cese < $ultimoDia) {
            $hasta = min((int) $cese->format('j'), self::DIAS_MES);
        }

        return max(0, $hasta - $desde + 1);
    }

    public static function trabajaEnElMes(DateTimeImmutable $ingreso, ?DateTimeImmutable $cese, int $anio, int $mes): bool
    {
        return self::proponer($ingreso, $cese, $anio, $mes) > 0;
    }
}
