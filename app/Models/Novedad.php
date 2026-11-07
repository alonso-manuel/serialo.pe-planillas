<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $planilla_linea_id
 * @property int $concepto_id
 * @property string $monto
 * @property string|null $glosa
 */
class Novedad extends Model
{
    use Auditable;

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

    /** @return BelongsTo<PlanillaLinea, $this> */
    public function linea(): BelongsTo
    {
        return $this->belongsTo(PlanillaLinea::class, 'planilla_linea_id');
    }

    /** La novedad pertenece a la empresa del periodo de su línea de planilla. */
    public function empresaIdAuditoria(): ?int
    {
        return $this->linea?->periodo?->empresa_id;
    }

    public function descripcionAuditoria(): string
    {
        $concepto = $this->concepto;

        return trim(($concepto ? "{$concepto->codigo_plame} {$concepto->nombre}" : 'Novedad')." · S/ {$this->monto}");
    }
}
