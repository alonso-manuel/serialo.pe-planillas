<?php

namespace App\Services\Ruc;

/**
 * Consulta de datos de un contribuyente en SUNAT a través de un proveedor intercambiable
 * (config `services.ruc.driver`). El RUC llega ya validado con App\Domain\Sunat\Ruc.
 *
 * @throws RucNoEncontrado si SUNAT no tiene registrado el RUC
 * @throws ConsultaRucNoDisponible si el proveedor no está configurado, no cubre el RUC o falla
 */
interface ConsultaRuc
{
    public function consultar(string $ruc): DatosRuc;
}
