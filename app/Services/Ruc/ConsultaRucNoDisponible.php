<?php

namespace App\Services\Ruc;

final class ConsultaRucNoDisponible extends \DomainException
{
    public static function caida(?\Throwable $causa = null): self
    {
        return new self('La consulta a SUNAT no está disponible ahora. Completa los datos a mano.', 0, $causa);
    }

    public static function sinConfigurar(): self
    {
        return new self('La consulta de RUC no está configurada. Completa los datos a mano.');
    }

    public static function soloJuridicas(): self
    {
        return new self('La consulta gratuita solo cubre personas jurídicas (RUC 20). Completa los datos a mano.');
    }
}
