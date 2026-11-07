<?php

namespace App\Domain\Planilla;

/** Tasas de la AFP del trabajador para el mes (publicadas por la SBS). */
final readonly class TasaAfpPeriodo
{
    public function __construct(
        public string $aporteObligatorio,
        public string $primaSeguro,
        public string $comision,          // flujo o mixta, según el tipo de comisión del trabajador
        public string $remuneracionMaximaAsegurable,
    ) {}
}
