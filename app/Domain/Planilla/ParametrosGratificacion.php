<?php

namespace App\Domain\Planilla;

/** Parámetros legales vigentes en el mes de pago de la gratificación. */
final readonly class ParametrosGratificacion
{
    public function __construct(
        public string $rmv,
        public string $porcentajeAsignacionFamiliar,
        /** Proporción de la gratificación según régimen: 1 (general), 0.5 (pequeña), 0 (micro). */
        public string $factorRegimen,
        /** Bonificación extraordinaria Ley 30334 (fracción, ej. 0.09). */
        public string $tasaBonificacion,
    ) {}
}
