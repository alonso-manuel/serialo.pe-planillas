<?php

namespace App\Policies;

use App\Models\Parametro;
use App\Models\User;

/** Parámetros legales: solo administradores. No hay `update`: las vigencias no se editan. */
class ParametroPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function delete(User $actor, Parametro $parametro): bool
    {
        return $actor->esAdmin();
    }
}
