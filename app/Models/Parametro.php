<?php

namespace App\Models;

use App\Enums\ClaveParametro;
use App\Models\Concerns\Auditable;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property ClaveParametro $clave
 * @property string $valor
 * @property CarbonImmutable $vigente_desde
 * @property string|null $base_legal
 */
class Parametro extends Model
{
    use Auditable;

    protected $fillable = ['clave', 'valor', 'vigente_desde', 'base_legal'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'clave' => ClaveParametro::class,
            'valor' => 'decimal:4',
            'vigente_desde' => 'date',
        ];
    }

    /** Valor vigente a una fecha (Y-m-d). Devuelve string decimal para usar con brick/math. */
    public static function vigente(ClaveParametro $clave, string $fecha): string
    {
        $valor = static::query()
            ->where('clave', $clave->value)
            ->where('vigente_desde', '<=', $fecha)
            ->orderByDesc('vigente_desde')
            ->value('valor');

        if ($valor === null) {
            throw new \RuntimeException("Falta el parámetro {$clave->value} vigente al {$fecha}.");
        }

        return (string) $valor;
    }

    public function descripcionAuditoria(): string
    {
        return $this->clave->etiqueta().' desde '.$this->vigente_desde->format('m/Y');
    }
}
