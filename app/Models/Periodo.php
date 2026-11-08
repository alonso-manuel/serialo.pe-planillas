<?php

namespace App\Models;

use App\Enums\EstadoPeriodo;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Carbon\CarbonImmutable;
use Database\Factories\PeriodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
 * @property-read User|null $cerradoPor
 */
class Periodo extends Model
{
    /** @use HasFactory<PeriodoFactory> */
    use Auditable, HasFactory, PerteneceAEmpresa;

    protected $fillable = ['empresa_id', 'anio', 'mes', 'estado', 'cerrado_por', 'cerrado_en'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'estado' => EstadoPeriodo::class,
            'cerrado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function cerradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cerrado_por');
    }

    /** @return HasMany<PlanillaLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(PlanillaLinea::class);
    }

    public const MESES = [
        1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
        'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre',
    ];

    /** "Noviembre 2026" */
    public function etiqueta(): string
    {
        return self::MESES[$this->mes]." {$this->anio}";
    }

    public function ultimoDia(): CarbonImmutable
    {
        return CarbonImmutable::create($this->anio, $this->mes, 1)->endOfMonth();
    }

    public function estaCerrado(): bool
    {
        return $this->estado === EstadoPeriodo::Cerrado;
    }

    public function descripcionAuditoria(): string
    {
        return sprintf('Periodo %02d/%d', $this->mes, $this->anio);
    }
}
