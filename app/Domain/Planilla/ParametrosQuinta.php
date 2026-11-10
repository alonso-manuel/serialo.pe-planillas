<?php

namespace App\Domain\Planilla;

/** Parámetros legales de la renta de quinta categoría vigentes en el mes (tabla `parametros`). */
final readonly class ParametrosQuinta
{
    /**
     * @param  string  $uit  UIT del año (S/)
     * @param  string  $deduccionUit  deducción anual en UIT (7)
     * @param  list<array{0: string|null, 1: string}>  $tramos  [hasta cuántas UIT (null = sin tope), tasa como fracción], en orden
     */
    public function __construct(
        public string $uit,
        public string $deduccionUit,
        public array $tramos,
    ) {}
}
