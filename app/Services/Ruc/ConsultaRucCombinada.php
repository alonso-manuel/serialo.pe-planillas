<?php

namespace App\Services\Ruc;

use App\Domain\Sunat\Ruc;

/**
 * Personas jurídicas (RUC 20) en OpenRUC, gratis e ilimitado; el resto (RUC 10, 15...)
 * en Decolecta, que tiene cupo mensual. Así el cupo se reserva casi todo para DNI.
 */
final class ConsultaRucCombinada implements ConsultaRuc
{
    public function __construct(
        private readonly ConsultaRuc $juridicas,
        private readonly ConsultaRuc $demas,
    ) {}

    public function consultar(string $ruc): DatosRuc
    {
        return Ruc::esPersonaJuridica($ruc)
            ? $this->juridicas->consultar($ruc)
            : $this->demas->consultar($ruc);
    }
}
