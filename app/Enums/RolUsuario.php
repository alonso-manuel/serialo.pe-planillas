<?php

namespace App\Enums;

enum RolUsuario: string
{
    /** Ve y configura todo el sistema. */
    case Admin = 'admin';

    /** Solo trabaja con las empresas que tiene asignadas (empresa_user). */
    case Asistente = 'asistente';

    public function etiqueta(): string
    {
        return match ($this) {
            self::Admin => 'Administrador',
            self::Asistente => 'Asistente',
        };
    }
}
