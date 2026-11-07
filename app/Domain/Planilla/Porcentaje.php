<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Conversión entre porcentaje (lo que ve y escribe el usuario: "1.37") y fracción
 * (lo que se guarda y usa el motor: "0.0137"). Sin float.
 */
final class Porcentaje
{
    /** Porcentaje con hasta 2 decimales → fracción con 4 decimales. */
    public static function aFraccion(string $porcentaje): string
    {
        return (string) BigDecimal::of($porcentaje)->dividedBy(100, 4, RoundingMode::Unnecessary);
    }

    /** Fracción guardada → porcentaje con 2 decimales. */
    public static function desdeFraccion(string $fraccion): string
    {
        return (string) BigDecimal::of($fraccion)->multipliedBy(100)->toScale(2, RoundingMode::HalfUp);
    }

    /** ¿Es un número no negativo con como máximo 2 decimales? */
    public static function esValido(string $valor): bool
    {
        return preg_match('/^\d{1,3}(\.\d{1,2})?$/', $valor) === 1;
    }

    public static function enRango(string $valor, string $minimo, string $maximo): bool
    {
        $numero = BigDecimal::of($valor);

        return $numero->isGreaterThanOrEqualTo($minimo) && $numero->isLessThanOrEqualTo($maximo);
    }
}
