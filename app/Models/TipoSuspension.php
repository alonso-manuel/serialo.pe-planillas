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
 * @property string|null $uso 'falta' | 'subsidio' | 'licencia' | null
 * @property bool $activo
 */
class TipoSuspension extends Model
{
    public const USO_FALTA = 'falta';

    public const USO_SUBSIDIO = 'subsidio';

    public const USO_LICENCIA = 'licencia';

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
        return self::codigoPara(self::USO_FALTA, 'faltas');
    }

    /** Código de la licencia sin goce de haber. */
    public static function codigoLicencia(): string
    {
        return self::codigoPara(self::USO_LICENCIA, 'licencias sin goce');
    }

    private static function codigoPara(string $uso, string $nombre): string
    {
        return (string) (static::query()->where('uso', $uso)->where('activo', true)->value('codigo')
            ?? throw new \RuntimeException("Falta en la tabla 21 el tipo de suspensión para {$nombre}."));
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
