<?php

namespace App\Services\Instalador;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * Estado de la instalación: si ya se instaló (marca en disco) y el código de instalación
 * que solo puede leer quien tiene acceso al hosting.
 */
class Instalacion
{
    private const MARCA = 'instalado.json';

    private const CODIGO = 'codigo-instalacion.txt';

    public function instalado(): bool
    {
        return (bool) config('instalador.omitir') || is_file($this->ruta(self::MARCA));
    }

    /** Fecha de instalación (inicio de la prueba sin licencia), o null si no hay marca. */
    public function instaladoEn(): ?CarbonImmutable
    {
        $archivo = $this->ruta(self::MARCA);

        if (! is_file($archivo)) {
            return null;
        }

        $datos = json_decode((string) file_get_contents($archivo), true);
        $fecha = is_array($datos) && is_string($datos['instalado_en'] ?? null) ? $datos['instalado_en'] : null;

        return $fecha === null ? null : CarbonImmutable::parse($fecha);
    }

    /** Ruta del código, tal como se le indica al usuario (relativa a la carpeta del sistema). */
    public function rutaCodigoVisible(): string
    {
        return ltrim(str_replace([base_path(), '\\'], ['', '/'], $this->ruta(self::CODIGO)), '/');
    }

    /** Crea el código si aún no existe (p. ej. «K7P2-9QXM-3HDA»). */
    public function asegurarCodigo(): void
    {
        if (is_file($this->ruta(self::CODIGO))) {
            return;
        }

        // Sin caracteres que se confundan (0/O, 1/I/L).
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $codigo = collect(range(1, 3))
            ->map(fn () => implode('', array_map(fn () => $alfabeto[random_int(0, strlen($alfabeto) - 1)], range(1, 4))))
            ->implode('-');

        $this->escribir(self::CODIGO, $codigo.PHP_EOL);
    }

    public function codigoValido(string $codigo): bool
    {
        $archivo = $this->ruta(self::CODIGO);

        if (! is_file($archivo)) {
            return false;
        }

        return hash_equals(
            Str::upper(trim((string) file_get_contents($archivo))),
            Str::upper(trim($codigo)),
        );
    }

    public function marcarInstalado(string $version): void
    {
        $this->escribir(self::MARCA, (string) json_encode([
            'instalado_en' => now()->toIso8601String(),
            'version' => $version,
        ], JSON_PRETTY_PRINT));

        @unlink($this->ruta(self::CODIGO));
    }

    private function ruta(string $archivo): string
    {
        return rtrim((string) config('instalador.directorio'), '/\\').DIRECTORY_SEPARATOR.$archivo;
    }

    private function escribir(string $archivo, string $contenido): void
    {
        $ruta = $this->ruta($archivo);

        if (! is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0755, true);
        }

        file_put_contents($ruta, $contenido, LOCK_EX);
    }
}
