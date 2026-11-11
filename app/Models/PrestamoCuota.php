<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuota de un préstamo, a descontar en la planilla de su mes.
 *
 * @property int $id
 * @property int $prestamo_id
 * @property int $numero
 * @property int $anio
 * @property int $mes
 * @property string $monto
 * @property-read Prestamo $prestamo
 */
class PrestamoCuota extends Model
{
    public $timestamps = false;

    protected $fillable = ['prestamo_id', 'numero', 'anio', 'mes', 'monto'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['numero' => 'integer', 'anio' => 'integer', 'mes' => 'integer', 'monto' => 'decimal:2'];
    }

    /** @return BelongsTo<Prestamo, $this> */
    public function prestamo(): BelongsTo
    {
        return $this->belongsTo(Prestamo::class);
    }
}
