<?php

namespace App\Models;

use App\Enums\TipoConcepto;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $codigo_plame
 * @property string $nombre
 * @property TipoConcepto $tipo
 * @property bool $afecto_essalud
 * @property bool $afecto_onp
 * @property bool $afecto_afp
 * @property bool $afecto_quinta
 * @property bool $sistema
 * @property bool $activo
 */
class Concepto extends Model
{
    protected $fillable = [
        'codigo_plame', 'nombre', 'tipo', 'afecto_essalud', 'afecto_onp',
        'afecto_afp', 'afecto_quinta', 'sistema', 'activo',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoConcepto::class,
            'afecto_essalud' => 'boolean',
            'afecto_onp' => 'boolean',
            'afecto_afp' => 'boolean',
            'afecto_quinta' => 'boolean',
            'sistema' => 'boolean',
            'activo' => 'boolean',
        ];
    }
}
