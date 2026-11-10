<?php

namespace App\Services\Instalador;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDO;
use Throwable;

/**
 * Conexión MySQL / MariaDB que el cliente indica en el instalador.
 *
 * @phpstan-type DatosBd array{host: string, puerto: int, nombre: string, usuario: string, clave: string|null}
 */
class ConexionBaseDatos
{
    /**
     * Prueba la conexión y que la base esté vacía. Devuelve el error para mostrar o null si está bien.
     *
     * @param  DatosBd  $datos
     */
    public function probar(array $datos): ?string
    {
        config(['database.connections.instalador' => $this->configuracion($datos)]);
        DB::purge('instalador');

        try {
            DB::connection('instalador')->getPdo();

            if (Schema::connection('instalador')->hasTable('users')) {
                return 'La base de datos ya tiene tablas de un sistema instalado. Usa una base de datos vacía.';
            }

            return null;
        } catch (Throwable $e) {
            $detalle = $e->getPrevious()?->getMessage() ?? $e->getMessage();

            return 'No se pudo conectar a la base de datos: '.mb_strimwidth($detalle, 0, 200, '…');
        } finally {
            DB::purge('instalador');
        }
    }

    /**
     * Deja la conexión como la predeterminada para migrar en esta misma petición.
     *
     * @param  DatosBd  $datos
     */
    public function configurar(array $datos): void
    {
        config([
            'database.connections.mysql' => $this->configuracion($datos),
            'database.default' => 'mysql',
        ]);
        DB::purge('mysql');
    }

    /**
     * @param  DatosBd  $datos
     * @return array<string, mixed>
     */
    private function configuracion(array $datos): array
    {
        return array_merge((array) config('database.connections.mysql'), [
            'driver' => 'mysql',
            'url' => null,
            'host' => $datos['host'],
            'port' => $datos['puerto'],
            'database' => $datos['nombre'],
            'username' => $datos['usuario'],
            'password' => $datos['clave'] ?? '',
            'options' => [PDO::ATTR_TIMEOUT => 5],
        ]);
    }
}
