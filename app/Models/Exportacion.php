<?php

namespace App\Models;

use App\Models\Concerns\PerteneceAEmpresa;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Archivo oficial generado (constancia con hash). Inmutable: solo se crea.
 *
 * @property int $id
 * @property string $tipo plame | afpnet
 * @property int $empresa_id
 * @property int|null $periodo_id
 * @property int $anio
 * @property int $mes
 * @property list<array{nombre: string, sha256: string, bytes: int}> $archivos
 * @property int|null $user_id
 * @property CarbonImmutable $created_at
 * @property-read User|null $usuario
 */
class Exportacion extends Model
{
    use PerteneceAEmpresa;

    public const PLAME = 'plame';

    public const UPDATED_AT = null;

    protected $table = 'exportaciones';

    protected $fillable = ['tipo', 'empresa_id', 'periodo_id', 'anio', 'mes', 'archivos', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archivos' => 'array'];
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
