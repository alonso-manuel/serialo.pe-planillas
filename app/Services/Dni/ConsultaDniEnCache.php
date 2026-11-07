<?php

namespace App\Services\Dni;

use Illuminate\Contracts\Cache\Repository;

/**
 * Recuerda cada consulta exitosa solo unos minutos (dato personal: lo mínimo necesario),
 * suficiente para no gastar cupo si se vuelve a pulsar el botón.
 */
final class ConsultaDniEnCache implements ConsultaDni
{
    public function __construct(
        private readonly ConsultaDni $proveedor,
        private readonly Repository $cache,
        private readonly int $segundos = 600,
    ) {}

    public function consultar(string $dni): DatosDni
    {
        $clave = 'consulta-dni:'.hash('sha256', $dni);
        $guardado = $this->cache->get($clave);

        if ($guardado instanceof DatosDni) {
            return $guardado;
        }

        $datos = $this->proveedor->consultar($dni);
        $this->cache->put($clave, $datos, $this->segundos);

        return $datos;
    }

    public function disponible(): bool
    {
        return $this->proveedor->disponible();
    }
}
