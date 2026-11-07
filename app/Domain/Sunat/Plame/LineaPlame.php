<?php

namespace App\Domain\Sunat\Plame;

/** Arma una línea con campos separados por "|" y terminada en "|", como exige el PLAME. */
final class LineaPlame
{
    public const FIN_DE_LINEA = "\r\n"; // los aplicativos de SUNAT corren en Windows (verificar)

    /** @param array<int, string|int> $campos */
    public static function de(array $campos): string
    {
        return implode('|', array_map('strval', $campos)).'|';
    }

    /** @param array<int, string> $lineas */
    public static function archivo(array $lineas): string
    {
        return $lineas === [] ? '' : implode(self::FIN_DE_LINEA, $lineas).self::FIN_DE_LINEA;
    }
}
