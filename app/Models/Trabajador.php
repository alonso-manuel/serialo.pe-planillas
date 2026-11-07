<?php

namespace App\Models;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $empresa_id
 * @property string $apellido_paterno
 * @property string|null $apellido_materno
 * @property string $nombres
 * @property string $remuneracion_basica
 * @property bool $asignacion_familiar
 * @property SistemaPension $sistema_pension
 * @property int|null $afp_id
 * @property string|null $tipo_comision_afp
 * @property SeguroSalud $seguro_salud
 * @property Carbon $fecha_ingreso
 * @property Carbon|null $fecha_cese
 * @property-read string $nombre_completo
 */
class Trabajador extends Model
{
    use SoftDeletes;

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
            'sistema_pension' => SistemaPension::class,
            'seguro_salud' => SeguroSalud::class,
        ];
    }

    /** @return Attribute<string, never> */
    protected function nombreCompleto(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->apellido_paterno} {$this->apellido_materno}, {$this->nombres}"));
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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
}
