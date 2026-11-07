<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Establecimiento extends Model
{
    /** Código SUNAT del establecimiento principal (domicilio fiscal). */
    public const CODIGO_PRINCIPAL = '0000';

    protected $fillable = ['empresa_id', 'codigo', 'nombre', 'direccion'];

    public function esPrincipal(): bool
    {
        return $this->codigo === self::CODIGO_PRINCIPAL;
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<Trabajador, $this> */
    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }
}
