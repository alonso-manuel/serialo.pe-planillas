<?php

namespace App\Models\Central;

use Stancl\Tenancy\Database\Models\Domain;

/** Subdominio de un estudio (solo la parte izquierda: «garcia» para garcia.serialo.pe). */
class Dominio extends Domain
{
    protected $table = 'dominios';
}
