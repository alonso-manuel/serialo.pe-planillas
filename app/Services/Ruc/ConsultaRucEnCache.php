<?php

namespace App\Services\Ruc;

use Illuminate\Contracts\Cache\Repository;

/**
 * Guarda 24 h cada consulta exitosa para no gastar el cupo del proveedor
 * (los fallos no se guardan, así se puede reintentar).
 */
final class ConsultaRucEnCache implements ConsultaRuc
{
    public function __construct(
        private readonly ConsultaRuc $proveedor,
        private readonly Repository $cache,
        private readonly int $segundos = 86400,
    ) {}

    public function consultar(string $ruc): DatosRuc
    {
        $clave = "consulta-ruc:{$ruc}";
        $guardado = $this->cache->get($clave);

        if ($guardado instanceof DatosRuc) {
            return $guardado;
        }

        $datos = $this->proveedor->consultar($ruc);
        $this->cache->put($clave, $datos, $this->segundos);

        return $datos;
    }
}
