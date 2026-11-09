<?php

namespace App\Policies;

use App\Models\LiquidacionCts;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorEmpresa;
use Illuminate\Database\Eloquent\Model;

/** CTS: se ve y calcula en las empresas visibles; cerrada es inmutable; cerrar y reabrir es del admin. */
class LiquidacionCtsPolicy
{
    use AutorizaPorEmpresa {
        update as private actualizarEnSuEmpresa;
    }

    public function update(User $user, Model $liquidacion): bool
    {
        return $liquidacion instanceof LiquidacionCts
            && ! $liquidacion->estaCerrada()
            && $this->actualizarEnSuEmpresa($user, $liquidacion);
    }

    public function cerrar(User $user, LiquidacionCts $liquidacion): bool
    {
        return $user->esAdmin() && ! $liquidacion->estaCerrada();
    }

    public function reabrir(User $user, LiquidacionCts $liquidacion): bool
    {
        return $user->esAdmin() && $liquidacion->estaCerrada();
    }
}
