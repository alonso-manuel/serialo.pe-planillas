<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Banco, caja o financiera donde se deposita la CTS (catálogo).
 *
 * @property int $id
 * @property string $codigo
 * @property string $nombre
 * @property bool $activo
 */
class EntidadFinanciera extends Model
{
    protected $table = 'entidades_financieras';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
