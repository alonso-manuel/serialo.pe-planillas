<?php

namespace App\Policies;

use App\Models\Empresa;
use App\Models\User;

/**
 * Empresas y sus establecimientos: solo administradores. Los asistentes trabajan
 * con sus empresas asignadas (selector de empresa activa) pero no editan su ficha.
 * Las empresas no se eliminan: se desactivan.
 */
class EmpresaPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function update(User $actor, Empresa $empresa): bool
    {
        return $actor->esAdmin();
    }
}
