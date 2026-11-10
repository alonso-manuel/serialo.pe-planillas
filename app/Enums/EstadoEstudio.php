<?php

namespace App\Enums;

/** Una cuenta suspendida no deja entrar a nadie; sus datos se conservan. */
enum EstadoEstudio: string
{
    case Activo = 'activo';
    case Suspendido = 'suspendido';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Activo => 'Activa',
            self::Suspendido => 'Suspendida',
        };
    }
}
