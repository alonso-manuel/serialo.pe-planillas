<?php

namespace App\Models;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Models\Concerns\Auditable;
use Database\Factories\EmpresaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $ruc
 * @property string $razon_social
 * @property RegimenLaboral $regimen_laboral
 * @property SeguroSalud $seguro_salud
 * @property bool $activo
 * @property string|null $direccion
 * @property string|null $logo_path ruta del logo en el disco privado (PNG reducido)
 */
class Empresa extends Model
{
    /** @use HasFactory<EmpresaFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'ruc', 'razon_social', 'nombre_comercial', 'direccion', 'ubigeo',
        'regimen_laboral', 'seguro_salud', 'representante_legal', 'logo_path', 'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'regimen_laboral' => RegimenLaboral::class,
            'seguro_salud' => SeguroSalud::class,
            'activo' => 'boolean',
        ];
    }

    /** @return HasMany<Establecimiento, $this> */
    public function establecimientos(): HasMany
    {
        return $this->hasMany(Establecimiento::class);
    }

    /** @return HasMany<Trabajador, $this> */
    public function trabajadores(): HasMany
    {
        return $this->hasMany(Trabajador::class);
    }

    /** @return HasMany<Periodo, $this> */
    public function periodos(): HasMany
    {
        return $this->hasMany(Periodo::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function usuarios(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function descripcionAuditoria(): string
    {
        return "{$this->razon_social} (RUC {$this->ruc})";
    }

    /** @return HasMany<CentroCosto, $this> */
    public function centrosCosto(): HasMany
    {
        return $this->hasMany(CentroCosto::class)->orderBy('codigo');
    }
}
