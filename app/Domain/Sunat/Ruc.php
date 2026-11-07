<?php

namespace App\Domain\Sunat;

/**
 * Registro Único de Contribuyentes (SUNAT).
 *
 * 11 dígitos: 2 de tipo de contribuyente + 8 + 1 dígito verificador (módulo 11).
 * Prefijos: 10 persona natural, 15 y 17 casos especiales de persona natural,
 * 16 sociedades conyugales/otros, 20 persona jurídica.
 */
final class Ruc
{
    /** @var list<string> */
    public const PREFIJOS = ['10', '15', '16', '17', '20'];

    /** @var list<int> */
    private const PESOS = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];

    public static function esValido(string $ruc): bool
    {
        if (preg_match('/^\d{11}$/', $ruc) !== 1) {
            return false;
        }

        if (! in_array(substr($ruc, 0, 2), self::PREFIJOS, true)) {
            return false;
        }

        return self::digitoVerificador(substr($ruc, 0, 10)) === (int) $ruc[10];
    }

    /** @param string $diezDigitos los 10 primeros dígitos del RUC */
    public static function digitoVerificador(string $diezDigitos): int
    {
        if (preg_match('/^\d{10}$/', $diezDigitos) !== 1) {
            throw new \InvalidArgumentException('Se esperaban 10 dígitos.');
        }

        $suma = 0;
        foreach (self::PESOS as $i => $peso) {
            $suma += (int) $diezDigitos[$i] * $peso;
        }

        $resto = 11 - ($suma % 11);

        return match ($resto) {
            10 => 0,
            11 => 1,
            default => $resto,
        };
    }

    /** Agrega el dígito verificador (útil para generar RUC ficticios en pruebas y demos). */
    public static function completar(string $diezDigitos): string
    {
        return $diezDigitos.self::digitoVerificador($diezDigitos);
    }

    public static function esPersonaJuridica(string $ruc): bool
    {
        return str_starts_with($ruc, '20');
    }
}
