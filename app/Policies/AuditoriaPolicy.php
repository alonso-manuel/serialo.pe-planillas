<?php

namespace App\Policies;

use App\Models\User;

/** La auditoría la consulta solo el admin. Nadie la edita ni la borra (no hay métodos para eso). */
class AuditoriaPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }
}
