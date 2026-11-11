<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;

/**
 * Retención de renta de cuarta categoría de un comprobante (LIR art. 74): el pagador retiene la tasa
 * (8 %) cuando el recibo por honorarios supera el monto mínimo (S/ 1,500), salvo que el prestador tenga
 * vigente su constancia de suspensión de retenciones. Notas de crédito, dietas y otros: sin retención ⚠.
 */
final class CalculadoraCuarta
{
    public const RECIBO = 'R';

    public function retencion(string $monto, string $tasa, string $montoMinimo, bool $suspendida, string $tipoComprobante = self::RECIBO): string
    {
        $importe = Monto::de($monto);

        if ($suspendida || $tipoComprobante !== self::RECIBO || ! $importe->isGreaterThan($montoMinimo)) {
            return (string) Monto::cero();
        }

        return Monto::texto($importe->multipliedBy($tasa));
    }

    public function neto(string $monto, string $retencion): string
    {
        return Monto::texto(BigDecimal::of($monto)->minus($retencion));
    }
}
