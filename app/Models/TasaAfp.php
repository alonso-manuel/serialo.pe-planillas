<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasaAfp extends Model
{
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
}
