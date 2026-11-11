<?php

namespace App\Models;

use App\Enums\DocumentoVinculo;
use App\Enums\MotivoBajaDerechohabiente;
use App\Enums\TipoDocumento;
use App\Enums\VinculoFamiliar;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Derechohabiente de un trabajador (cónyuge, concubino(a), gestante, hijos) para EsSalud y el T-Registro.
 *
 * @property int $id
 * @property int $trabajador_id
 * @property TipoDocumento $tipo_documento
 * @property string $numero_documento
 * @property string|null $pais_emisor
 * @property CarbonImmutable $fecha_nacimiento
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $nombres
 * @property string $sexo M / F
 * @property VinculoFamiliar $vinculo
 * @property DocumentoVinculo|null $documento_vinculo
 * @property string|null $numero_documento_vinculo
 * @property string|null $mes_concepcion AAAA-MM
 * @property CarbonImmutable $fecha_alta
 * @property CarbonImmutable|null $fecha_baja
 * @property MotivoBajaDerechohabiente|null $motivo_baja
 * @property-read Trabajador $trabajador
 * @property-read string $nombre_completo
 */
class Derechohabiente extends Model
{
    use Auditable;

    protected $table = 'derechohabientes';

    protected $fillable = [
        'trabajador_id', 'tipo_documento', 'numero_documento', 'pais_emisor', 'fecha_nacimiento',
        'apellido_paterno', 'apellido_materno', 'nombres', 'sexo', 'vinculo', 'documento_vinculo',
        'numero_documento_vinculo', 'mes_concepcion', 'fecha_alta', 'fecha_baja', 'motivo_baja',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo_documento' => TipoDocumento::class,
            'vinculo' => VinculoFamiliar::class,
            'documento_vinculo' => DocumentoVinculo::class,
            'motivo_baja' => MotivoBajaDerechohabiente::class,
            'fecha_nacimiento' => 'immutable_date',
            'fecha_alta' => 'immutable_date',
            'fecha_baja' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno}").", {$this->nombres}";
    }

    public function activo(): bool
    {
        return $this->fecha_baja === null;
    }

    public function descripcionAuditoria(): string
    {
        return "Derechohabiente {$this->getNombreCompletoAttribute()} ({$this->vinculo->etiqueta()})";
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->trabajador->empresa_id;
    }
}
