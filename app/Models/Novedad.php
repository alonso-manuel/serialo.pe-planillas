<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Novedad extends Model
{
    protected $table = 'novedades';

    protected $fillable = ['planilla_linea_id', 'concepto_id', 'monto', 'glosa'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    /** @return BelongsTo<Concepto, $this> */
    public function concepto(): BelongsTo
    {
        return $this->belongsTo(Concepto::class);
    }
}
