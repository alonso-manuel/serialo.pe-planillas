<?php

namespace App\Services\Dni;

final class ConsultaDniNoDisponible extends \DomainException
{
    public static function caida(?\Throwable $causa = null): self
    {
        return new self('La consulta a RENIEC no está disponible ahora. Completa los datos a mano.', 0, $causa);
    }

    public static function sinConfigurar(): self
    {
        return new self('La consulta de DNI no está configurada. Completa los datos a mano.');
    }

    public static function sinCupo(): self
    {
        return new self('Se agotaron las consultas de DNI del mes. Completa los datos a mano.');
    }
}
