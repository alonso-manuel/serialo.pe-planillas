<?php

namespace App\Domain\Planilla;

/** Ingreso manual del mes (bono, comisión, etc.) con su afectación. */
final readonly class IngresoAdicional
{
    public function __construct(
        public string $monto,
        public bool $afectoPension = true,
        public bool $afectoEssalud = true,
    ) {}
}
