<?php

namespace App\Enums;

/** Esquema de comisión elegido por el afiliado a una AFP. */
enum TipoComisionAfp: string
{
    case Flujo = 'flujo';   // comisión sobre la remuneración
    case Mixta = 'mixta';   // comisión sobre remuneración (menor) + sobre saldo

    public function etiqueta(): string
    {
        return match ($this) {
            self::Flujo => 'Comisión por flujo',
            self::Mixta => 'Comisión mixta',
        };
    }
}
