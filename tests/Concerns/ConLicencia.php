<?php

namespace Tests\Concerns;

use App\Domain\Licencia\ClaveLicencia;
use App\Services\Instalador\Instalacion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Activa la verificación de licencia en un test (en testing viene omitida) con un par de claves propio,
 * una carpeta temporal para la clave y la marca de instalación.
 */
trait ConLicencia
{
    private string $privadaLicencia;

    private string $carpetaLicencia;

    /** @param array<string, int|null> $limitesPrueba */
    protected function activarLicencia(string $instaladoEn = '2026-11-01', array $limitesPrueba = []): void
    {
        $par = sodium_crypto_sign_keypair();
        $this->privadaLicencia = base64_encode(sodium_crypto_sign_secretkey($par));
        $this->carpetaLicencia = storage_path('framework/testing/licencia-'.Str::random(8));
        File::ensureDirectoryExists($this->carpetaLicencia);

        config([
            'licencia.omitir' => false,
            'licencia.clave_publica' => base64_encode(sodium_crypto_sign_publickey($par)),
            'licencia.archivo' => $this->carpetaLicencia.'/licencia.txt',
            'licencia.prueba' => ['dias' => 15, 'max_empresas' => 1, 'max_usuarios' => 2, 'max_trabajadores' => 50, ...$limitesPrueba],
            'instalador.directorio' => $this->carpetaLicencia,
        ]);

        $this->travelTo($instaladoEn.' 09:00');
        app(Instalacion::class)->marcarInstalado('1.0.0');
        $this->travelBack();

        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($this->carpetaLicencia));
    }

    /** @param array<string, mixed> $cambios */
    protected function claveLicencia(array $cambios = []): string
    {
        return ClaveLicencia::firmar([
            'cliente' => 'Estudio Contable Ejemplo S.A.C.',
            'plan' => 'Estudio',
            'max_empresas' => 40,
            'max_usuarios' => 3,
            'max_trabajadores' => null,
            'dominio' => 'localhost',
            'emitida' => '2026-11-09',
            'actualizaciones_hasta' => '2027-11-09',
            ...$cambios,
        ], $this->privadaLicencia);
    }

    /** @param array<string, mixed> $cambios */
    protected function guardarClave(array $cambios = []): void
    {
        file_put_contents((string) config('licencia.archivo'), $this->claveLicencia($cambios));
    }
}
