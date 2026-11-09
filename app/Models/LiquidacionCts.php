<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * CTS de una empresa para un depósito (mayo o noviembre). Cerrada = inmutable (como un periodo).
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $anio
 * @property int $mes 5 | 11
 * @property string $estado borrador | cerrada
 * @property CarbonImmutable|null $calculado_en
 * @property int|null $cerrado_por
 * @property CarbonImmutable|null $cerrado_en
 * @property-read Empresa|null $empresa
 * @property-read User|null $cerradoPor
 */
class LiquidacionCts extends Model
{
    use Auditable, PerteneceAEmpresa;

    public const BORRADOR = 'borrador';

    public const CERRADA = 'cerrada';

    protected $table = 'liquidaciones_cts';

    protected $fillable = ['empresa_id', 'anio', 'mes', 'estado', 'calculado_en', 'cerrado_por', 'cerrado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['calculado_en' => 'datetime', 'cerrado_en' => 'datetime'];
    }

    /** @return HasMany<LiquidacionCtsLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(LiquidacionCtsLinea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    public function estaCerrada(): bool
    {
        return $this->estado === self::CERRADA;
    }

    /** "Mayo 2027" */
    public function etiqueta(): string
    {
        return Periodo::MESES[$this->mes]." {$this->anio}";
    }

    public function descripcionAuditoria(): string
    {
        return sprintf('CTS %02d/%d', $this->mes, $this->anio);
    }
}
