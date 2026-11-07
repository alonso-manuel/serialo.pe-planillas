<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tasas guardadas como fracción con 4 decimales (0.0137); RMA en soles.
 *
 * @property int $id
 * @property int $afp_id
 * @property int $anio
 * @property int $mes
 * @property string $aporte_obligatorio
 * @property string $prima_seguro
 * @property string $comision_flujo
 * @property string $comision_mixta
 * @property string $remuneracion_maxima_asegurable
 */
class TasaAfp extends Model
{
    use Auditable;

    protected $table = 'tasas_afp';

    protected $fillable = [
        'afp_id', 'anio', 'mes', 'aporte_obligatorio', 'prima_seguro',
        'comision_flujo', 'comision_mixta', 'remuneracion_maxima_asegurable',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'aporte_obligatorio' => 'decimal:4',
            'prima_seguro' => 'decimal:4',
            'comision_flujo' => 'decimal:4',
            'comision_mixta' => 'decimal:4',
            'remuneracion_maxima_asegurable' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Afp, $this> */
    public function afp(): BelongsTo
    {
        return $this->belongsTo(Afp::class);
    }

    public function descripcionAuditoria(): string
    {
        return ($this->afp->nombre ?? 'AFP').' '.sprintf('%02d/%d', $this->mes, $this->anio);
    }
}
