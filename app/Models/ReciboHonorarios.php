<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Recibo por honorarios (u otro comprobante de la tabla 23) pagado en un periodo, con su retención de cuarta.
 *
 * @property int $id
 * @property int $periodo_id
 * @property int $prestador_id
 * @property string $tipo_comprobante R recibo, N nota de crédito, D dieta, O otro
 * @property string $serie
 * @property string $numero
 * @property CarbonImmutable $fecha_emision
 * @property CarbonImmutable $fecha_pago
 * @property string $monto
 * @property string $retencion
 * @property-read Periodo $periodo
 * @property-read PrestadorServicio $prestador
 */
class ReciboHonorarios extends Model
{
    use Auditable;

    protected $table = 'recibos_honorarios';

    protected $fillable = ['periodo_id', 'prestador_id', 'tipo_comprobante', 'serie', 'numero', 'fecha_emision', 'fecha_pago', 'monto', 'retencion'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_emision' => 'immutable_date', 'fecha_pago' => 'immutable_date', 'monto' => 'decimal:2', 'retencion' => 'decimal:2'];
    }

    /** @return BelongsTo<Periodo, $this> */
    public function periodo(): BelongsTo
    {
        return $this->belongsTo(Periodo::class);
    }

    /** @return BelongsTo<PrestadorServicio, $this> */
    public function prestador(): BelongsTo
    {
        return $this->belongsTo(PrestadorServicio::class, 'prestador_id');
    }

    public function descripcionAuditoria(): string
    {
        return "Recibo {$this->tipo_comprobante} {$this->serie}-{$this->numero}";
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->periodo->empresa_id;
    }
}
