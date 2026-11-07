<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Afp extends Model
{
    protected $fillable = ['codigo', 'nombre', 'codigo_sunat', 'activo'];

    /** @return HasMany<TasaAfp, $this> */
    public function tasas(): HasMany
    {
        return $this->hasMany(TasaAfp::class);
    }

    public function tasaDe(int $anio, int $mes): ?TasaAfp
    {
        return $this->tasas()->where(compact('anio', 'mes'))->first();
    }
}
