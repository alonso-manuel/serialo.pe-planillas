<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Ayudante para montos en soles. REGLA DEL PROYECTO: nunca usar float para dinero.
 * Todo monto entra y sale como string decimal y se calcula con brick/math.
 */
final class Monto
{
    public static function de(string|int|BigDecimal $valor): BigDecimal
    {
        return BigDecimal::of($valor);
    }

    /** Redondeo a 2 decimales, mitad hacia arriba (criterio usado en el PLAME). */
    public static function redondear(BigDecimal $valor): BigDecimal
    {
        return $valor->toScale(2, RoundingMode::HalfUp);
    }

    public static function texto(BigDecimal $valor): string
    {
        return (string) self::redondear($valor);
    }

    public static function cero(): BigDecimal
    {
        return BigDecimal::zero()->toScale(2);
    }
}
