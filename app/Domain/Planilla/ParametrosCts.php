<?php

namespace App\Domain\Planilla;

/** Parámetros legales vigentes al cierre del periodo de la CTS. */
final readonly class ParametrosCts
{
    public function __construct(
        public string $rmv,
        public string $porcentajeAsignacionFamiliar,
        /** Proporción de la CTS según régimen: 1 (general), 0.5 (pequeña), 0 (micro). */
        public string $factorRegimen,
    ) {}
}
