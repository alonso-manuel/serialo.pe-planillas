<?php

namespace App\Policies;

use App\Models\Liquidacion;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorEmpresa;
use Illuminate\Database\Eloquent\Model;

/** Liquidación de beneficios: se ve y calcula en las empresas visibles; cerrada es inmutable; cerrar y reabrir es del admin. */
class LiquidacionPolicy
{
    use AutorizaPorEmpresa {
        update as private actualizarEnSuEmpresa;
    }

    public function update(User $user, Model $liquidacion): bool
    {
        return $liquidacion instanceof Liquidacion
            && ! $liquidacion->estaCerrada()
            && $this->actualizarEnSuEmpresa($user, $liquidacion);
    }

    public function cerrar(User $user, Liquidacion $liquidacion): bool
    {
        return $user->esAdmin() && ! $liquidacion->estaCerrada() && $this->mismaEmpresa($user, $liquidacion);
    }

    public function reabrir(User $user, Liquidacion $liquidacion): bool
    {
        return $user->esAdmin() && $liquidacion->estaCerrada() && $this->mismaEmpresa($user, $liquidacion);
    }
}
