<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un campo que cambió en la ficha del trabajador, con el mes desde el que rige.
 * Los campos de un mismo cambio (p. ej. pensión) comparten `grupo` y `vigente_desde`.
 *
 * @property int $id
 * @property int $trabajador_id
 * @property string $grupo
 * @property string $campo
 * @property string|null $valor_anterior
 * @property string|null $valor_nuevo
 * @property CarbonImmutable $vigente_desde
 * @property int|null $user_id
 * @property-read Trabajador $trabajador
 * @property-read User|null $usuario
 */
class TrabajadorHistorial extends Model
{
    use Auditable;

    protected $table = 'trabajador_historial';

    protected $fillable = ['trabajador_id', 'grupo', 'campo', 'valor_anterior', 'valor_nuevo', 'vigente_desde', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['vigente_desde' => 'date'];
    }

    /** @return BelongsTo<Trabajador, $this> */
    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function empresaIdAuditoria(): ?int
    {
        return $this->trabajador->empresa_id;
    }

    public function descripcionAuditoria(): string
    {
        return "Cambio de {$this->campo} de {$this->trabajador->nombre_completo} desde ".$this->vigente_desde->format('m/Y');
    }
}
