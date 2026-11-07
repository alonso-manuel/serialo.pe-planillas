<?php

namespace App\Models\Concerns;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Para modelos con columna `empresa_id` (Trabajador, Periodo...).
 * Toda consulta de un módulo debe pasar por `deEmpresa()` (empresa activa)
 * o `visiblesPara()` (listados que cruzan empresas, p. ej. el panel del estudio).
 *
 * @property int $empresa_id
 */
trait PerteneceAEmpresa
{
    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * @param  Builder<static>  $query
     */
    public function scopeDeEmpresa(Builder $query, int $empresaId): void
    {
        $query->where($this->qualifyColumn('empresa_id'), $empresaId);
    }

    /**
     * Solo registros de empresas que el usuario puede ver (todas si es admin).
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisiblesPara(Builder $query, User $user): void
    {
        if (! $user->esAdmin()) {
            $query->whereIn($this->qualifyColumn('empresa_id'), $user->empresas()->select('empresas.id'));
        }
    }
}
