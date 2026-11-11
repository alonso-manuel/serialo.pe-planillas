<?php

namespace App\Domain\Planilla;

/** Parámetros del régimen agrario (Ley 31110) vigentes al cierre del periodo. Tasas como fracción. */
final readonly class ParametrosAgrario
{
    public function __construct(
        /** EsSalud agrario según el tamaño del empleador (art. 9 de la Ley; art. 57 del reglamento). */
        public string $tasaEssalud,
        /** BETA: fracción de la RMV (0.30). */
        public string $porcentajeBeta,
        /** Proporción mensual de las gratificaciones sobre la RB (0.1666). */
        public string $porcentajeGratificacion,
        /** Proporción mensual de la CTS sobre la RB (0.0972). */
        public string $porcentajeCts,
        /** Bonificación extraordinaria de la Ley 30334 sobre la gratificación (0.09). */
        public string $tasaBonificacion,
    ) {}
}
