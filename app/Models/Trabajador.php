<?php

namespace App\Models;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Carbon\CarbonImmutable;
use Database\Factories\TrabajadorFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $empresa_id
 * @property int|null $establecimiento_id
 * @property TipoDocumento $tipo_documento
 * @property string $numero_documento
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $nombres
 * @property CarbonImmutable|null $fecha_nacimiento
 * @property string|null $sexo
 * @property string|null $cargo
 * @property TipoContrato $tipo_contrato
 * @property string $remuneracion_basica
 * @property bool $asignacion_familiar
 * @property SistemaPension $sistema_pension
 * @property int|null $afp_id
 * @property string|null $cuspp
 * @property TipoComisionAfp|null $tipo_comision_afp
 * @property SeguroSalud $seguro_salud
 * @property CarbonImmutable $fecha_ingreso
 * @property CarbonImmutable|null $fecha_cese
 * @property string|null $motivo_cese
 * @property string|null $cuenta_sueldo
 * @property string|null $cuenta_cts
 * @property string|null $email
 * @property string|null $telefono
 * @property-read string $nombre_completo
 */
class Trabajador extends Model
{
    /** @use HasFactory<TrabajadorFactory> */
    use Auditable, HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'trabajadores';

    protected $fillable = [
        'empresa_id', 'establecimiento_id', 'tipo_documento', 'numero_documento',
        'apellido_paterno', 'apellido_materno', 'nombres', 'fecha_nacimiento', 'sexo',
        'cargo', 'fecha_ingreso', 'fecha_cese', 'motivo_cese', 'tipo_contrato',
        'remuneracion_basica', 'asignacion_familiar', 'sistema_pension', 'afp_id',
        'cuspp', 'tipo_comision_afp', 'seguro_salud', 'cuenta_sueldo', 'cuenta_cts',
        'email', 'telefono',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'fecha_cese' => 'date',
            'remuneracion_basica' => 'decimal:2',
            'asignacion_familiar' => 'boolean',
            'tipo_documento' => TipoDocumento::class,
            'tipo_contrato' => TipoContrato::class,
            'sistema_pension' => SistemaPension::class,
            'tipo_comision_afp' => TipoComisionAfp::class,
            'seguro_salud' => SeguroSalud::class,
        ];
    }

    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->apellido_paterno} {$this->apellido_materno}, {$this->nombres}"));
    }

    /** @return HasMany<TrabajadorHistorial, $this> */
    public function historial(): HasMany
    {
        return $this->hasMany(TrabajadorHistorial::class);
    }

    /** @return BelongsTo<Establecimiento, $this> */
    public function establecimiento(): BelongsTo
    {
        return $this->belongsTo(Establecimiento::class);
    }

    /** ¿Tiene cese registrado a la fecha indicada? */
    public function estaCesado(?CarbonImmutable $fecha = null): bool
    {
        return $this->fecha_cese !== null && $this->fecha_cese->lte($fecha ?? CarbonImmutable::today());
    }

    /** @return BelongsTo<Afp, $this> */
    public function afp(): BelongsTo
    {
        return $this->belongsTo(Afp::class);
    }

    /** @return HasMany<PlanillaLinea, $this> */
    public function lineas(): HasMany
    {
        return $this->hasMany(PlanillaLinea::class);
    }

    public function descripcionAuditoria(): string
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno}, {$this->nombres}")." (doc. {$this->numero_documento})";
    }
}
