<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CTS de un trabajador en una liquidación.
 *
 * @property int $id
 * @property int $liquidacion_cts_id
 * @property int $trabajador_id
 * @property CarbonImmutable $computo_desde
 * @property CarbonImmutable $computo_hasta
 * @property int $meses
 * @property int $dias
 * @property int $dias_descontados
 * @property string $remuneracion_basica
 * @property string $asignacion_familiar
 * @property string $sexto_gratificacion
 * @property string $promedio_variables
 * @property string $remuneracion_computable
 * @property string $monto
 * @property string|null $entidad_cts
 * @property string|null $cuenta_cts
 * @property list<string>|null $avisos
 * @property-read Trabajador $trabajador
 * @property-read LiquidacionCts $liquidacion
 */
class LiquidacionCtsLinea extends Model
{
    protected $table = 'liquidacion_cts_lineas';

    protected $fillable = [
        'liquidacion_cts_id', 'trabajador_id', 'computo_desde', 'computo_hasta', 'meses', 'dias', 'dias_descontados',
        'remuneracion_basica', 'asignacion_familiar', 'sexto_gratificacion', 'promedio_variables',
        'remuneracion_computable', 'monto', 'entidad_cts', 'cuenta_cts', 'avisos',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'computo_desde' => 'date', 'computo_hasta' => 'date',
            'remuneracion_basica' => 'decimal:2', 'asignacion_familiar' => 'decimal:2', 'sexto_gratificacion' => 'decimal:2',
            'promedio_variables' => 'decimal:2', 'remuneracion_computable' => 'decimal:2', 'monto' => 'decimal:2',
            'avisos' => 'array',
        ];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /** @return BelongsTo<LiquidacionCts, $this> */
    public function liquidacion(): BelongsTo
    {
        return $this->belongsTo(LiquidacionCts::class, 'liquidacion_cts_id');
    }
}
