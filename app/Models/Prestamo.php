<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Préstamo al trabajador, descontado en cuotas mensuales (concepto 0706).
 *
 * @property int $id
 * @property int $trabajador_id
 * @property CarbonImmutable $fecha
 * @property string $monto
 * @property int $numero_cuotas
 * @property string|null $glosa
 * @property-read Trabajador $trabajador
 * @property-read Collection<int, PrestamoCuota> $cuotas
 */
class Prestamo extends Model
{
    use Auditable;

    protected $fillable = ['trabajador_id', 'fecha', 'monto', 'numero_cuotas', 'glosa'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha' => 'immutable_date', 'monto' => 'decimal:2', 'numero_cuotas' => 'integer'];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /** @return HasMany<PrestamoCuota, $this> */
    public function cuotas(): HasMany
    {
        return $this->hasMany(PrestamoCuota::class)->orderBy('numero');
    }

    public function descripcionAuditoria(): string
    {
        return "Préstamo de S/ {$this->monto} en {$this->numero_cuotas} cuotas";
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->trabajador->empresa_id;
    }
}
