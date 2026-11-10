<?php

namespace App\Models\Central;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Registro de lo que hace el superadmin en el panel central. Inmutable.
 *
 * @property int $id
 * @property int|null $superadmin_id
 * @property string|null $estudio_id
 * @property string $accion
 * @property string $descripcion
 * @property array<string, mixed>|null $cambios
 * @property string|null $ip
 * @property CarbonImmutable $created_at
 * @property-read Superadmin|null $superadmin
 */
class AuditoriaCentral extends Model
{
    use CentralConnection;

    public const UPDATED_AT = null;

    protected $table = 'auditorias_centrales';

    protected $fillable = ['superadmin_id', 'estudio_id', 'accion', 'descripcion', 'cambios', 'ip'];

    protected static function booted(): void
    {
        static::updating(fn () => false);
        static::deleting(fn () => false);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    public static function registrar(string $accion, string $descripcion, ?string $estudioId = null, array $cambios = []): void
    {
        $superadmin = auth('superadmin')->user();

        self::query()->create([
            'superadmin_id' => $superadmin instanceof Superadmin ? $superadmin->id : null,
            'estudio_id' => $estudioId,
            'accion' => $accion,
            'descripcion' => mb_substr($descripcion, 0, 255),
            'cambios' => $cambios === [] ? null : $cambios,
            'ip' => app()->bound('request') ? request()->ip() : null,
        ]);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['cambios' => 'array'];
    }

    /** @return BelongsTo<Superadmin, $this> */
    public function superadmin(): BelongsTo
    {
        return $this->belongsTo(Superadmin::class);
    }
}
