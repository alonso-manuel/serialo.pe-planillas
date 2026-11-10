<?php

namespace App\Domain\Licencia;

/** Lo que la licencia limita. Solo cuenta lo activo: desactivar o cesar libera cupo. */
enum Recurso: string
{
    case Empresas = 'empresas';
    case Usuarios = 'usuarios';
    case Trabajadores = 'trabajadores';

    /** Campo del JSON de la licencia. */
    public function campo(): string
    {
        return 'max_'.$this->value;
    }

    public function etiqueta(): string
    {
        return match ($this) {
            self::Empresas => 'Empresas activas',
            self::Usuarios => 'Usuarios activos',
            self::Trabajadores => 'Trabajadores activos',
        };
    }

    public function plural(int $cantidad): string
    {
        return match ($this) {
            self::Empresas => $cantidad === 1 ? 'empresa activa' : 'empresas activas',
            self::Usuarios => $cantidad === 1 ? 'usuario activo' : 'usuarios activos',
            self::Trabajadores => $cantidad === 1 ? 'trabajador activo' : 'trabajadores activos',
        };
    }
}
