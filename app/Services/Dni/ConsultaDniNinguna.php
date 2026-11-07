<?php

namespace App\Services\Dni;

/** Sin proveedor (o sin token): los nombres se escriben a mano. */
final class ConsultaDniNinguna implements ConsultaDni
{
    public function consultar(string $dni): DatosDni
    {
        throw ConsultaDniNoDisponible::sinConfigurar();
    }

    public function disponible(): bool
    {
        return false;
    }
}
