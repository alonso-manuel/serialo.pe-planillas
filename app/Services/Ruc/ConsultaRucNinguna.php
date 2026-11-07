<?php

namespace App\Services\Ruc;

/** Sin proveedor: el formulario se completa a mano. */
final class ConsultaRucNinguna implements ConsultaRuc
{
    public function consultar(string $ruc): DatosRuc
    {
        throw ConsultaRucNoDisponible::sinConfigurar();
    }
}
