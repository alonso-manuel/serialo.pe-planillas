<?php

namespace App\Services\Dni;

final class DniNoEncontrado extends \DomainException
{
    public function __construct()
    {
        parent::__construct('RENIEC no devolvió datos para ese DNI. Revisa el número o completa a mano.');
    }
}
