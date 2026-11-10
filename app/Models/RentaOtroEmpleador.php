<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Renta y retención de quinta categoría que el trabajador percibió en otro empleador durante el año
 * (certificado de rentas y retenciones). Se suma a la proyección de la retención.
 *
 * @property int $id
 * @property int $trabajador_id
 * @property int $anio
 * @property string|null $ruc_empleador
 * @property string|null $empleador
 * @property string $renta
 * @property string $retencion
 * @property CarbonImmutable $created_at
 * @property-read Trabajador $trabajador
 */
class RentaOtroEmpleador extends Model
{
    use Auditable;

    protected $table = 'rentas_otros_empleadores';

    protected $fillable = ['trabajador_id', 'anio', 'ruc_empleador', 'empleador', 'renta', 'retencion'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['anio' => 'integer', 'renta' => 'decimal:2', 'retencion' => 'decimal:2'];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function descripcionAuditoria(): string
    {
        return "Renta de otro empleador {$this->anio}".($this->empleador ? " ({$this->empleador})" : '');
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->trabajador->empresa_id;
    }
}
