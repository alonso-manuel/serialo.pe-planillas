<?php

namespace App\Services\Ruc;

final class RucNoEncontrado extends \DomainException
{
    public function __construct()
    {
        parent::__construct('SUNAT no tiene registrado ese RUC.');
    }
}
