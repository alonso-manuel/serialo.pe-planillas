<?php

namespace App\Domain\Planilla;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Monto en letras para la boleta: "1205.50" → "MIL DOSCIENTOS CINCO CON 50/100 SOLES".
 * Hasta 999 999 999.99 (suficiente para cualquier remuneración).
 */
final class MontoEnLetras
{
    private const UNIDADES = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve',
        'diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve',
        'veinte', 'veintiuno', 'veintidós', 'veintitrés', 'veinticuatro', 'veinticinco', 'veintiséis', 'veintisiete',
        'veintiocho', 'veintinueve'];

    private const DECENAS = ['', '', '', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];

    private const CENTENAS = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos',
        'setecientos', 'ochocientos', 'novecientos'];

    public static function soles(string $monto): string
    {
        $valor = BigDecimal::of($monto)->toScale(2, RoundingMode::HalfUp);
        if ($valor->isNegative()) {
            throw new \InvalidArgumentException('El monto no puede ser negativo.');
        }

        [$entero, $centimos] = explode('.', (string) $valor);
        $numero = (int) $entero;
        if ($numero > 999_999_999) {
            throw new \InvalidArgumentException('Monto demasiado grande.');
        }

        $letras = $numero === 0 ? 'cero' : self::numero($numero);

        return mb_strtoupper("{$letras} con {$centimos}/100 soles");
    }

    private static function numero(int $n): string
    {
        $millones = intdiv($n, 1_000_000);
        $miles = intdiv($n % 1_000_000, 1000);
        $resto = $n % 1000;

        $partes = [];
        if ($millones > 0) {
            $partes[] = $millones === 1 ? 'un millón' : self::apocopar(self::hastaMil($millones)).' millones';
        }
        if ($miles > 0) {
            $partes[] = $miles === 1 ? 'mil' : self::apocopar(self::hastaMil($miles)).' mil';
        }
        if ($resto > 0) {
            $partes[] = self::hastaMil($resto);
        }

        return implode(' ', $partes);
    }

    /** 1–999 en letras. */
    private static function hastaMil(int $n): string
    {
        if ($n === 100) {
            return 'cien';
        }

        $centena = intdiv($n, 100);
        $resto = $n % 100;

        $texto = self::CENTENAS[$centena];
        if ($resto > 0) {
            $decenas = $resto < 30
                ? self::UNIDADES[$resto]
                : self::DECENAS[intdiv($resto, 10)].($resto % 10 > 0 ? ' y '.self::UNIDADES[$resto % 10] : '');
            $texto = trim("{$texto} {$decenas}");
        }

        return $texto;
    }

    /** "uno" delante de mil/millones: "veintiún mil", "treinta y un mil". */
    private static function apocopar(string $texto): string
    {
        return (string) preg_replace(['/veintiuno$/u', '/uno$/u'], ['veintiún', 'un'], $texto);
    }
}
