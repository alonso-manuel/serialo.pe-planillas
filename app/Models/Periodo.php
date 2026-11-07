<?php

namespace App\Models;

use App\Enums\EstadoPeriodo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int $anio
 * @property int $mes
 * @property EstadoPeriodo $estado
 * @property int|null $cerrado_por
 * @property CarbonImmutable|null $cerrado_en
 */
class Periodo extends Model
{
    protected $fillable = ['empresa_id', 'anio', 'mes', 'estado', 'cerrado_por', 'cerrado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPeriodo::class,
            'cerrado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<PlanillaLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(PlanillaLinea::class);
    }

    public function ultimoDia(): CarbonImmutable
    {
        return CarbonImmutable::create($this->anio, $this->mes, 1)->endOfMonth();
    }

    public function estaCerrado(): bool
    {
        return $this->estado === EstadoPeriodo::Cerrado;
    }
}
