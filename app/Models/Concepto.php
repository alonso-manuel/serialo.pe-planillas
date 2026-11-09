<?php

namespace App\Models;

use App\Enums\TipoConcepto;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $codigo_plame
 * @property string $nombre
 * @property TipoConcepto $tipo
 * @property bool $afecto_essalud
 * @property bool $afecto_onp
 * @property bool $afecto_afp
 * @property bool $afecto_quinta
 * @property string|null $cuenta_cargo cuenta contable del gasto (PCGE)
 * @property string|null $cuenta_abono cuenta contable del pasivo/activo (PCGE)
 * @property bool $sistema
 * @property bool $activo
 */
class Concepto extends Model
{
    use Auditable;

    protected $fillable = [
        'codigo_plame', 'nombre', 'tipo', 'afecto_essalud', 'afecto_onp',
        'afecto_afp', 'afecto_quinta', 'cuenta_cargo', 'cuenta_abono', 'sistema', 'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConcepto::class,
            'afecto_essalud' => 'boolean',
            'afecto_onp' => 'boolean',
            'afecto_afp' => 'boolean',
            'afecto_quinta' => 'boolean',
            'sistema' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    public const AFECTACIONES = ['afecto_essalud', 'afecto_onp', 'afecto_afp', 'afecto_quinta'];

    /** @return HasMany<Novedad, $this> */
    public function novedades(): HasMany
    {
        return $this->hasMany(Novedad::class);
    }

    /** @return HasMany<PlanillaConcepto, $this> */
    public function resultados(): HasMany
    {
        return $this->hasMany(PlanillaConcepto::class);
    }

    /** ¿Ya aparece en alguna novedad o resultado de planilla? (entonces no se elimina) */
    public function estaEnUso(): bool
    {
        return $this->novedades()->exists() || $this->resultados()->exists();
    }

    public function descripcionAuditoria(): string
    {
        return "{$this->codigo_plame} {$this->nombre}";
    }
}
