<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Registro de auditoría (regla 7). Inmutable: no se edita ni se elimina.
 *
 * @property int $id
 * @property int|null $user_id
 * @property int|null $empresa_id
 * @property string $auditable_type
 * @property int $auditable_id
 * @property string|null $descripcion
 * @property string $accion
 * @property array<string, array{0: mixed, 1: mixed}>|null $cambios
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 * @property-read User|null $usuario
 * @property-read Empresa|null $empresa
 */
class Auditoria extends Model
{
    public const UPDATED_AT = null;

    public const ACCIONES = [
        'creado' => 'Creado',
        'actualizado' => 'Actualizado',
        'eliminado' => 'Eliminado',
        'restaurado' => 'Restaurado',
        'cerrado' => 'Cerrado',
        'reabierto' => 'Reabierto',
    ];

    private static bool $pausada = false;

    protected $fillable = ['user_id', 'empresa_id', 'auditable_type', 'auditable_id', 'descripcion', 'accion', 'cambios', 'ip'];

    protected static function booted(): void
    {
        // Inmutable: cualquier intento de editar o borrar se cancela.
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /**
     * Ejecuta sin registrar auditoría (seeders, instalador, datos de prueba).
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function sinRegistrar(callable $callback): mixed
    {
        $anterior = self::$pausada;
        self::$pausada = true;

        try {
            return $callback();
        } finally {
            self::$pausada = $anterior;
        }
    }

    public static function estaPausada(): bool
    {
        return self::$pausada;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cambios' => 'array'];
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Empresa, $this> */
    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }
}
