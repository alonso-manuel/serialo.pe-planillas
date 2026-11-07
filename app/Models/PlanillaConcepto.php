<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
