<?php

namespace App\Models\Central;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Comentario de un usuario sobre el sistema (modo SaaS). Lo envía desde su estudio y se lee en el panel central.
 *
 * @property int $id
 * @property string|null $estudio_id null si la cuenta ya se eliminó
 * @property string $estudio_nombre
 * @property int $usuario_id
 * @property string $usuario_nombre
 * @property string $usuario_correo
 * @property string $usuario_rol
 * @property int|null $calificacion 1 a 5
 * @property string $comentario
 * @property CarbonImmutable|null $leida_en
 * @property CarbonImmutable $created_at
 */
class Opinion extends Model
{
    use CentralConnection;

    protected $table = 'opiniones';

    protected $fillable = [
        'estudio_id', 'estudio_nombre', 'usuario_id', 'usuario_nombre', 'usuario_correo', 'usuario_rol',
        'calificacion', 'comentario', 'leida_en',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['calificacion' => 'integer', 'leida_en' => 'immutable_datetime', 'created_at' => 'immutable_datetime'];
    }
}
