<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Monto calculado de un concepto para un trabajador en un periodo (boleta y PLAME).
 *
 * @property int $id
 * @property int $planilla_linea_id
 * @property int $concepto_id
 * @property string $monto_devengado
 * @property string $monto_pagado
 * @property-read Concepto $concepto
 */
class PlanillaConcepto extends Model
{
    protected $fillable = ['planilla_linea_id', 'concepto_id', 'monto_devengado', 'monto_pagado'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'monto_devengado' => 'decimal:2',
            'monto_pagado' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Concepto, $this> */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class);
    }
}
