<?php

namespace App\Domain\Sunat\Plame;

/** Nombre oficial de los archivos de importación del PLAME: 0601 + AAAA + MM + RUC + extensión. */
final class NombreArchivoPlame
{
    public static function para(string $ruc, int $anio, int $mes, string $extension): string
    {
        return sprintf('0601%04d%02d%s.%s', $anio, $mes, $ruc, ltrim($extension, '.'));
    }
}
