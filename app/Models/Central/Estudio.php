<?php

namespace App\Models\Central;

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
 */
class Estudio extends Tenant implements TenantWithDatabase
{
    use HasDatabase;
    use HasDomains;

    protected $table = 'estudios';

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
        return ['id', 'nombre', 'created_at', 'updated_at'];
    }
}
