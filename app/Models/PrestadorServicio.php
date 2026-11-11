<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Prestador de servicios con rentas de cuarta categoría (emite recibos por honorarios a la empresa).
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $tipo_documento tabla 3 (06 RUC si es domiciliado)
 * @property string $numero_documento
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $nombres
 * @property bool $domiciliado
 * @property int|null $suspension_anio ejercicio de su constancia de suspensión de retenciones
 * @property-read string $nombre_completo
 */
class PrestadorServicio extends Model
{
    use Auditable;

    protected $table = 'prestadores_servicio';

    protected $fillable = ['empresa_id', 'tipo_documento', 'numero_documento', 'apellido_paterno', 'apellido_materno', 'nombres', 'domiciliado', 'suspension_anio'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['domiciliado' => 'boolean', 'suspension_anio' => 'integer'];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<ReciboHonorarios, $this> */
    public function recibos(): HasMany
    {
        return $this->hasMany(ReciboHonorarios::class, 'prestador_id');
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno}").", {$this->nombres}";
    }

    /** ¿Tiene constancia de suspensión de retenciones para el año del pago? */
    public function suspendidoEn(int $anio): bool
    {
        return $this->suspension_anio === $anio;
    }

    public function descripcionAuditoria(): string
    {
        return "Prestador {$this->numero_documento} {$this->getNombreCompletoAttribute()}";
    }
}
