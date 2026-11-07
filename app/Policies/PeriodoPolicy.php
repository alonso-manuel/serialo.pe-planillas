<?php

namespace App\Policies;

use App\Models\Periodo;
use App\Models\User;
use App\Policies\Concerns\AutorizaPorEmpresa;
use Illuminate\Database\Eloquent\Model;

/**
 * Periodos de planilla.
 * - Regla 5: un periodo cerrado es inmutable (nadie edita novedades ni recalcula).
 * - Cerrar y reabrir es solo del admin.
 */
class PeriodoPolicy
{
    use AutorizaPorEmpresa {
        update as private actualizarEnSuEmpresa;
    }

    public function update(User $user, Model $periodo): bool
    {
        return $periodo instanceof Periodo
            && ! $periodo->estaCerrado()
            && $this->actualizarEnSuEmpresa($user, $periodo);
    }

    public function cerrar(User $user, Periodo $periodo): bool
    {
        return $user->esAdmin() && ! $periodo->estaCerrado();
    }

    public function reabrir(User $user, Periodo $periodo): bool
    {
        return $user->esAdmin() && $periodo->estaCerrado();
    }
}
