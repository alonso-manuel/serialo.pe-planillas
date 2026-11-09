<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tabla 21 de SUNAT: tipos de suspensión de la relación laboral (archivo .snl del PLAME).
 *
 * @property int $id
 * @property string $codigo
 * @property string $descripcion
 * @property string|null $uso 'falta' | 'subsidio' | null
 * @property bool $activo
 */
class TipoSuspension extends Model
{
    public const USO_FALTA = 'falta';

    public const USO_SUBSIDIO = 'subsidio';

    protected $table = 'tipos_suspension';

    protected $fillable = ['codigo', 'descripcion', 'uso', 'activo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** Código de las faltas injustificadas. */
    public static function codigoFalta(): string
    {
        return (string) (static::query()->where('uso', self::USO_FALTA)->where('activo', true)->value('codigo')
            ?? throw new \RuntimeException('Falta en la tabla 21 el tipo de suspensión para faltas.'));
    }

    /**
     * Tipos elegibles para los días subsidiados.
     *
     * @param  Builder<TipoSuspension>  $query
     */
    public function scopeSubsidios(Builder $query): void
    {
        $query->where('uso', self::USO_SUBSIDIO)->where('activo', true)->orderBy('codigo');
    }
}
