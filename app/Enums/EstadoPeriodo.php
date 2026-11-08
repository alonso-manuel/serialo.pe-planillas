<?php

namespace App\Enums;

enum EstadoPeriodo: string
{
    case Abierto = 'abierto';
    case Calculado = 'calculado';
    case Cerrado = 'cerrado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Abierto => 'Abierto',
            self::Calculado => 'Calculado',
            self::Cerrado => 'Cerrado',
        };
    }
}
