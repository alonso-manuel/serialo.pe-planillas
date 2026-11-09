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

    public const AFPNET = 'afpnet';

    public const UPDATED_AT = null;

    protected $table = 'exportaciones';

    protected $fillable = ['tipo', 'empresa_id', 'periodo_id', 'anio', 'mes', 'archivos', 'user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['archivos' => 'array'];
    }

    /**
     * Deja constancia de una exportación con el hash SHA-256 de cada archivo.
     *
     * @param  array<string, string>  $archivos  nombre => contenido
     */
    public static function registrar(string $tipo, Periodo $periodo, array $archivos, User $usuario): self
    {
        return self::query()->create([
            'tipo' => $tipo,
            'empresa_id' => $periodo->empresa_id,
            'periodo_id' => $periodo->id,
            'anio' => $periodo->anio,
            'mes' => $periodo->mes,
            'archivos' => array_map(
                fn (string $nombre, string $contenido) => ['nombre' => $nombre, 'sha256' => hash('sha256', $contenido), 'bytes' => strlen($contenido)],
                array_keys($archivos),
                $archivos,
            ),
            'user_id' => $usuario->id,
        ]);
    }

    /** @return BelongsTo<User, $this> */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
