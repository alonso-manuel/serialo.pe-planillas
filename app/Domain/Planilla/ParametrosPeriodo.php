<?php

namespace App\Domain\Planilla;

/** Parámetros legales vigentes al último día del periodo (ver App\Enums\ClaveParametro). */
final readonly class ParametrosPeriodo
{
    public function __construct(
        public string $rmv,
        public string $tasaEssalud,
        public string $aporteSis,
        public string $tasaOnp,
        public string $porcentajeAsignacionFamiliar,
        public string $sobretasaHoraExtra25,
        public string $sobretasaHoraExtra35,
        /** Solo empresas del régimen agrario. */
        public ?ParametrosAgrario $agrario = null,
    ) {}
}
