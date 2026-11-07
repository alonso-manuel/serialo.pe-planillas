<?php

namespace App\Policies;

use App\Models\User;

/** Tasas AFP mensuales (SBS): solo administradores. */
class TasaAfpPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }
}
