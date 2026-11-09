<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Provisión mensual estimada de beneficios sociales para el costo laboral (no es un pago). Clase PURA.
 * ⚠ Estimación sobre la remuneración del mes (básico + asignación familiar), según el régimen:
 * - Gratificación: computable × factor / 6 (dos gratificaciones al año = 1/6 por mes).
 * - Bonificación extraordinaria: gratificación × tasa (9 %).
 * - CTS: (computable + 1/6 de la gratificación semestral) × factor / 12.
 * - Vacaciones: básico × (días de derecho / 30) / 12.
 */
final class ProvisionesMensuales
{
    /** @return array{gratificacion: string, bonificacion: string, cts: string, vacaciones: string, total: string} */
    public static function calcular(
        string $basico,
        string $asignacionFamiliar,
        string $factorGratificacion,
        string $tasaBonificacion,
        string $factorCts,
        int $diasVacaciones,
    ): array {
        $computable = Monto::de($basico)->plus(Monto::de($asignacionFamiliar));

        // Gratificación semestral y su provisión mensual.
        $graSemestral = $computable->multipliedBy($factorGratificacion);
        $gratificacion = Monto::redondear($graSemestral->dividedBy(6, 10, RoundingMode::HalfUp));
        $bonificacion = Monto::redondear($gratificacion->multipliedBy($tasaBonificacion));

        $cts = Monto::redondear(
            $computable->plus($graSemestral->dividedBy(6, 10, RoundingMode::HalfUp))
                ->multipliedBy($factorCts)
                ->dividedBy(12, 10, RoundingMode::HalfUp)
        );

        $vacaciones = Monto::redondear(
            Monto::de($basico)->multipliedBy($diasVacaciones)->dividedBy(30 * 12, 10, RoundingMode::HalfUp)
        );

        $total = array_reduce([$gratificacion, $bonificacion, $cts, $vacaciones], fn (BigDecimal $s, BigDecimal $m) => $s->plus($m), Monto::cero());

        return [
            'gratificacion' => Monto::texto($gratificacion),
            'bonificacion' => Monto::texto($bonificacion),
            'cts' => Monto::texto($cts),
            'vacaciones' => Monto::texto($vacaciones),
            'total' => Monto::texto($total),
        ];
    }
}
