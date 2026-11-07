<?php

namespace App\Policies;

use App\Models\Concepto;
use App\Models\User;

/** Conceptos de la tabla 22: solo administradores (las reglas de `sistema` van en el servicio). */
class ConceptoPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->esAdmin();
    }

    public function update(User $actor, Concepto $concepto): bool
    {
        return $actor->esAdmin();
    }

    public function delete(User $actor, Concepto $concepto): bool
    {
        return $actor->esAdmin();
    }
}
