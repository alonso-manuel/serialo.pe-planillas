<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Constancia de envío de una boleta por correo.
 *
 * @property int $id
 * @property int $planilla_linea_id
 * @property string $email
 * @property string $estado pendiente | enviado | fallido
 * @property string|null $error
 * @property CarbonImmutable|null $enviado_en
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 * @property-read PlanillaLinea $linea
 */
class EnvioBoleta extends Model
{
    public const PENDIENTE = 'pendiente';

    public const ENVIADO = 'enviado';

    public const FALLIDO = 'fallido';

    protected $table = 'envios_boleta';

    protected $fillable = ['planilla_linea_id', 'email', 'estado', 'error', 'enviado_en', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['enviado_en' => 'datetime'];
    }

    /** @return BelongsTo<PlanillaLinea, $this> */
    public function linea(): BelongsTo
    {
        return $this->belongsTo(PlanillaLinea::class, 'planilla_linea_id');
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
