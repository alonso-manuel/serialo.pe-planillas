<?php

namespace App\Models\Central;

use App\Enums\EstadoEstudio;
use App\Enums\TipoEstudio;
use Carbon\CarbonImmutable;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant;

/**
 * Cliente del modo SaaS (estudio contable, empresa o demo). Vive en la base central;
 * todas sus planillas, usuarios y archivos están en su propia base y carpeta.
 *
 * @property string $id subdominio
 * @property string $nombre
 * @property TipoEstudio $tipo
 * @property EstadoEstudio $estado
 * @property CarbonImmutable|null $vence_en
 * @property string|null $correo_admin
 * @property CarbonImmutable|null $suspendido_en
 * @property CarbonImmutable $created_at
 */
class Estudio extends Tenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $table = 'estudios';

    /** @var array<string, mixed> */
    protected $attributes = [
        'tipo' => 'cliente',
        'estado' => 'activo',
    ];

    // La clave es el subdominio (texto elegido al crear), no un autoincremental ni un UUID.
    public function getIncrementing(): bool
    {
        return false;
    }

    public function shouldGenerateId(): bool
    {
        return false;
    }

    public function getKeyType(): string
    {
        return 'string';
    }

    /** @return list<string> */
    public static function getCustomColumns(): array
    {
        return ['id', 'nombre', 'tipo', 'estado', 'vence_en', 'correo_admin', 'suspendido_en', 'created_at', 'updated_at'];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tipo' => TipoEstudio::class,
            'estado' => EstadoEstudio::class,
            'vence_en' => 'immutable_date',
            'suspendido_en' => 'immutable_datetime',
        ];
    }

    public function suspendido(): bool
    {
        return $this->estado === EstadoEstudio::Suspendido;
    }

    /** Dirección pública del estudio según config('planillas.url_estudio'): https://garcia.serialo.pe */
    public function url(string $ruta = ''): string
    {
        return str_replace('{estudio}', $this->id, (string) config('planillas.url_estudio')).$ruta;
    }
}
