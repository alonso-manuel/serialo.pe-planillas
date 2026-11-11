<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Certificado digital de una empresa para firmar sus documentos laborales en PDF.
 * El certificado y su clave se guardan cifrados (APP_KEY), nunca se muestran ni se auditan.
 *
 * @property int $id
 * @property int $empresa_id
 * @property string $titular
 * @property string|null $documento
 * @property string $emisor
 * @property string $numero_serie
 * @property string $huella
 * @property CarbonImmutable $valido_desde
 * @property CarbonImmutable $valido_hasta
 * @property string $certificado contenido del .p12 en base64 (descifrado por el cast)
 * @property string $clave
 * @property int|null $registrado_por
 * @property CarbonImmutable $created_at
 * @property-read Empresa $empresa
 */
class FirmaDigital extends Model
{
    use Auditable;

    protected $table = 'firmas_digitales';

    protected $fillable = [
        'empresa_id', 'titular', 'documento', 'emisor', 'numero_serie', 'huella',
        'valido_desde', 'valido_hasta', 'certificado', 'clave', 'registrado_por',
    ];

    protected $hidden = ['certificado', 'clave'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'valido_desde' => 'immutable_datetime',
            'valido_hasta' => 'immutable_datetime',
            'certificado' => 'encrypted',
            'clave' => 'encrypted',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function vigente(): bool
    {
        // 5 minutos de tolerancia por diferencias de reloj con la entidad que lo emitió.
        return $this->valido_desde->subMinutes(5)->isPast() && $this->valido_hasta->isFuture();
    }

    public function descripcionAuditoria(): string
    {
        return "Firma digital de {$this->titular}";
    }
}
