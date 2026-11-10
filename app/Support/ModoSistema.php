<?php

namespace App\Support;

/**
 * Cómo está desplegado el sistema (config/planillas.php → modo):
 * - «unica»: una instalación en el hosting del cliente (instalador web + licencia firmada).
 * - «saas»: el VPS de Serialo.pe, un estudio por subdominio con su propia base (multitenant).
 */
final class ModoSistema
{
    public const UNICA = 'unica';

    public const SAAS = 'saas';

    public static function saas(): bool
    {
        return config('planillas.modo') === self::SAAS;
    }
}
