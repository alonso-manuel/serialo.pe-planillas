<?php

namespace App\Enums;

enum EstadoPeriodo: string
{
    case Abierto = 'abierto';
    case Calculado = 'calculado';
    case Cerrado = 'cerrado';
}
