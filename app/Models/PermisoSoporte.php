<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Autorización del estudio para que Serialo.pe entre como soporte (modo SaaS).
 *
 * @property int $id
 * @property int|null $autorizado_por
 * @property CarbonImmutable $vence_en
 * @property CarbonImmutable|null $revocado_en
 * @property string|null $token_hash
 * @property CarbonImmutable|null $token_vence
 * @property CarbonImmutable $created_at
 * @property-read User|null $autorizador
 */
class PermisoSoporte extends Model
{
    protected $table = 'permisos_soporte';

    protected $fillable = ['autorizado_por', 'vence_en', 'revocado_en', 'token_hash', 'token_vence'];

    protected $hidden = ['token_hash'];

    /** El permiso en curso: no revocado y sin vencer. */
    public static function vigente(): ?self
    {
        $permiso = self::query()
            ->whereNull('revocado_en')
            ->where('vence_en', '>', now())
            ->latest('id')
            ->first();

        return $permiso instanceof self ? $permiso : null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'vence_en' => 'immutable_datetime',
            'revocado_en' => 'immutable_datetime',
            'token_vence' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function autorizador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }
}
