<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un trabajador en un periodo: entradas del mes (días, horas) y totales calculados.
 *
 * @property int $id
 * @property int $periodo_id
 * @property int $trabajador_id
 * @property int $dias_laborados
 * @property int $dias_no_laborados
 * @property int $dias_subsidiados
 * @property string $horas_extra_25
 * @property string $horas_extra_35
 * @property string $total_ingresos
 * @property string $total_descuentos
 * @property string $total_aportes_empleador
 * @property string $neto_pagar
 * @property CarbonImmutable|null $calculado_en
 * @property-read Periodo $periodo
 * @property-read Trabajador $trabajador
 */
class PlanillaLinea extends Model
{
    use Auditable;

    protected $fillable = [
        'periodo_id', 'trabajador_id', 'dias_laborados', 'dias_no_laborados', 'dias_subsidiados',
        'horas_ordinarias', 'minutos_ordinarios', 'horas_extra_25', 'horas_extra_35',
        'total_ingresos', 'total_descuentos', 'total_aportes_empleador', 'neto_pagar', 'calculado_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'horas_extra_25' => 'decimal:2',
            'horas_extra_35' => 'decimal:2',
            'total_ingresos' => 'decimal:2',
            'total_descuentos' => 'decimal:2',
            'total_aportes_empleador' => 'decimal:2',
            'neto_pagar' => 'decimal:2',
            'calculado_en' => 'datetime',
        ];
    }

    /** @return BelongsTo<Periodo, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /** @return HasMany<Novedad, $this> */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    /** @return HasMany<PlanillaConcepto, $this> */
    public function conceptos(): HasMany
    {
        return $this->hasMany(PlanillaConcepto::class);
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->periodo->empresa_id;
    }

    public function descripcionAuditoria(): string
    {
        return "{$this->trabajador->nombre_completo} · {$this->periodo->etiqueta()}";
    }
}
