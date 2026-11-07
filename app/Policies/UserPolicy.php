<?php

namespace App\Policies;

use App\Models\User;

/**
 * Gestión de usuarios: solo administradores. Los usuarios nunca se eliminan
 * (se desactivan) para conservar la autoría en auditoría y cierres de periodo.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function update(User $actor, User $usuario): bool
    {
        return $actor->esAdmin();
    }
}
