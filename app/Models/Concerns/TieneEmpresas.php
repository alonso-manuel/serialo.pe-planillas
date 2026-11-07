<?php

namespace App\Models\Concerns;

use App\Enums\RolUsuario;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Agregar al modelo User:  use \App\Models\Concerns\TieneEmpresas;
 * y en $fillable: 'rol', 'activo'.
 */
trait TieneEmpresas
{
    /** @return BelongsToMany<Empresa, $this> */
    public function empresas(): BelongsToMany
    {
        return $this->belongsToMany(Empresa::class);
    }

    public function esAdmin(): bool
    {
        return $this->rol === RolUsuario::Admin;
    }

    /**
     * Empresas que el usuario puede ver: todas si es admin, solo las asignadas si es asistente.
     *
     * @return Builder<Empresa>
     */
    public function empresasVisibles(): Builder
    {
        return $this->esAdmin()
            ? Empresa::query()
            : Empresa::query()->whereIn('id', $this->empresas()->select('empresas.id'));
    }

    public function puedeVerEmpresa(Empresa $empresa): bool
    {
        return $this->puedeVerEmpresaId($empresa->id);
    }

    /** Igual que puedeVerEmpresa() sin cargar la empresa (para policies de modelos con empresa_id). */
    public function puedeVerEmpresaId(int $empresaId): bool
    {
        return $this->esAdmin() || $this->empresas()->whereKey($empresaId)->exists();
    }
}
