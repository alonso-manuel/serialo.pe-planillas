<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/** Formato de montos para mostrar (PDF, mensajes). Nunca usa float. */
final class Formato
{
    /** "1205.5" → "1,205.50" */
    public static function monto(string|int|null $valor): string
    {
        $numero = BigDecimal::of((string) ($valor ?? '0'))->toScale(2, RoundingMode::HalfUp);
        $negativo = $numero->isNegative();
        [$entero, $decimales] = explode('.', (string) $numero->abs());

        return ($negativo ? '-' : '').strrev(implode(',', str_split(strrev($entero), 3))).'.'.$decimales;
    }
}
