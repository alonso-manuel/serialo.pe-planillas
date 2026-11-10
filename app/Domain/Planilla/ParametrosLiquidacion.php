<?php

namespace App\Domain\Planilla;

/** Parámetros legales vigentes a la fecha de cese, según el régimen de la empresa. */
final readonly class ParametrosLiquidacion
{
    public function __construct(
        public string $rmv,
        public string $porcentajeAsignacionFamiliar,
        /** Proporción de la gratificación: 1 (general), 0.5 (pequeña), 0 (micro). */
        public string $factorGratificacion,
        /** Proporción de la CTS: 1 (general), 0.5 (pequeña), 0 (micro). */
        public string $factorCts,
        /** Bonificación extraordinaria (fracción, ej. 0.09). */
        public string $tasaBonificacion,
        /** Días de vacaciones por año del régimen (30 o 15): base de las vacaciones truncas. */
        public int $diasVacaciones,
    ) {}

    public function gratificacion(): ParametrosGratificacion
    {
        return new ParametrosGratificacion($this->rmv, $this->porcentajeAsignacionFamiliar, $this->factorGratificacion, $this->tasaBonificacion);
    }

    public function cts(): ParametrosCts
    {
        return new ParametrosCts($this->rmv, $this->porcentajeAsignacionFamiliar, $this->factorCts);
    }
}
