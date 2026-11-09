<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PerteneceAEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Descanso vacacional gozado (o programado) por un trabajador: días calendario desde la fecha de inicio.
 *
 * @property int $id
 * @property int $empresa_id
 * @property int $trabajador_id
 * @property CarbonImmutable $fecha_inicio
 * @property int $dias
 * @property CarbonImmutable $fecha_fin
 * @property string|null $observacion
 * @property int|null $user_id
 * @property-read Trabajador $trabajador
 */
class VacacionGoce extends Model
{
    use Auditable, PerteneceAEmpresa;

    protected $table = 'vacaciones_goce';

    protected $fillable = ['empresa_id', 'trabajador_id', 'fecha_inicio', 'dias', 'fecha_fin', 'observacion', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['fecha_inicio' => 'date', 'fecha_fin' => 'date'];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function descripcionAuditoria(): string
    {
        return "Vacaciones de {$this->trabajador->nombre_completo}: {$this->dias} días desde {$this->fecha_inicio->format('d/m/Y')}";
    }
}
