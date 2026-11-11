<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Centro de costo de una empresa (área, obra, sede...): agrupa el costo de la planilla y el asiento contable.
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 * @property-read Empresa $empresa
 */
class CentroCosto extends Model
{
    use Auditable;

    protected $table = 'centros_costo';

    protected $fillable = ['empresa_id', 'codigo', 'nombre', 'activo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /** @return HasMany<Trabajador, $this> */
    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }

    public function descripcionAuditoria(): string
    {
        return "Centro de costo {$this->codigo} {$this->nombre}";
    }
}
