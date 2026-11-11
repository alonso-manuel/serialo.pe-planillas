<?php

namespace App\Models;

use App\Enums\CategoriaOcupacional;
use App\Enums\ModalidadContrato;
use App\Enums\MotivoCese;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\SituacionEducativa;
use App\Enums\SituacionEspecial;
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
 * @property int|null $centro_costo_id vigente hoy (por mes: ver el historial)
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
 * @property MotivoCese|null $motivo_cese
 * @property string|null $cuenta_sueldo
 * @property string|null $entidad_sueldo banco de la cuenta de sueldo (entidades_financieras)
 * @property string|null $cuenta_cts
 * @property string|null $entidad_cts código de entidades_financieras
 * @property string|null $email
 * @property string|null $telefono
 * @property CategoriaOcupacional|null $categoria_ocupacional tabla 24 de SUNAT
 * @property string|null $ocupacion tabla 30 de SUNAT
 * @property SituacionEducativa|null $situacion_educativa
 * @property ModalidadContrato|null $modalidad_contrato tabla 12 (plazo fijo)
 * @property SituacionEspecial $situacion_especial
 * @property bool $discapacidad
 * @property bool $sindicalizado
 * @property string|null $nacionalidad tabla 4 (extranjeros)
 * @property string|null $pais_emisor tabla 26 (pasaporte)
 * @property-read string $nombre_completo
 */
class Trabajador extends Model
{
    /** @use HasFactory<TrabajadorFactory> */
    use Auditable, HasFactory, PerteneceAEmpresa, SoftDeletes;

    protected $table = 'trabajadores';

    protected $fillable = [
        'empresa_id', 'establecimiento_id', 'centro_costo_id', 'tipo_documento', 'numero_documento',
        'apellido_paterno', 'apellido_materno', 'nombres', 'fecha_nacimiento', 'sexo',
        'cargo', 'fecha_ingreso', 'fecha_cese', 'motivo_cese', 'tipo_contrato',
        'remuneracion_basica', 'asignacion_familiar', 'sistema_pension', 'afp_id',
        'cuspp', 'tipo_comision_afp', 'seguro_salud', 'cuenta_sueldo', 'entidad_sueldo', 'cuenta_cts', 'entidad_cts',
        'email', 'telefono',
        // T-Registro
        'categoria_ocupacional', 'ocupacion', 'situacion_educativa', 'modalidad_contrato', 'situacion_especial',
        'discapacidad', 'sindicalizado', 'nacionalidad', 'pais_emisor',
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
            'discapacidad' => 'boolean',
            'sindicalizado' => 'boolean',
            'categoria_ocupacional' => CategoriaOcupacional::class,
            'situacion_educativa' => SituacionEducativa::class,
            'modalidad_contrato' => ModalidadContrato::class,
            'situacion_especial' => SituacionEspecial::class,
            'tipo_documento' => TipoDocumento::class,
            'tipo_contrato' => TipoContrato::class,
            'sistema_pension' => SistemaPension::class,
            'tipo_comision_afp' => TipoComisionAfp::class,
            'motivo_cese' => MotivoCese::class,
            'seguro_salud' => SeguroSalud::class,
        ];
    }

    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim(implode(' ', array_filter([$this->apellido_paterno, $this->apellido_materno])).', '.$this->nombres));
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

    /** @return HasMany<RentaOtroEmpleador, $this> */
    public function rentasOtrosEmpleadores(): HasMany
    {
        return $this->hasMany(RentaOtroEmpleador::class);
    }

    /** @return HasMany<Derechohabiente, $this> */
    public function derechohabientes(): HasMany
    {
        return $this->hasMany(Derechohabiente::class);
    }

    /** @return HasMany<Prestamo, $this> */
    public function prestamos(): HasMany
    {
        return $this->hasMany(Prestamo::class);
    }

    /** @return BelongsTo<CentroCosto, $this> */
    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class);
    }
}
