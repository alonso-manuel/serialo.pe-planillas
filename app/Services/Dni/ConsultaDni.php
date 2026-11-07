<?php

namespace App\Services\Dni;

/**
 * Consulta de nombres por DNI (RENIEC) a través de un proveedor intercambiable.
 * Solo se usan apellidos y nombres para completar la ficha (Ley 29733: dato mínimo necesario).
 *
 * @throws DniNoEncontrado
 * @throws ConsultaDniNoDisponible
 */
interface ConsultaDni
{
    public function consultar(string $dni): DatosDni;

    /** ¿Hay un proveedor configurado? (para mostrar u ocultar el botón). */
    public function disponible(): bool;
}
