<?php

namespace App\Enums;

/** Tipo de cuenta en el modo SaaS. */
enum TipoEstudio: string
{
    case Demo = 'demo';
    case Cliente = 'cliente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Demo => 'Demo',
            self::Cliente => 'Cliente',
        };
    }
}
