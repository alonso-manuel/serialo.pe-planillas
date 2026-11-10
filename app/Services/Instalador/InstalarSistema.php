<?php

namespace App\Services\Instalador;

use App\Enums\RolUsuario;
use App\Models\Auditoria;
use App\Models\User;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Instala el sistema en la base de datos indicada: migraciones, catálogos legales, primer
 * administrador y `.env` definitivo. El `.env` se escribe solo si todo lo anterior salió bien.
 *
 * @phpstan-import-type DatosBd from ConexionBaseDatos
 *
 * @phpstan-type DatosSistema array{nombre: string, url: string, smtp_host: string|null, smtp_puerto: int|null, smtp_usuario: string|null, smtp_clave: string|null, smtp_cifrado: string|null, correo_remitente: string|null}
 * @phpstan-type DatosAdmin array{nombre: string, correo: string, clave: string}
 */
class InstalarSistema
{
    public function __construct(
        private readonly ConexionBaseDatos $conexion,
        private readonly ArchivoEnv $env,
        private readonly Instalacion $instalacion,
    ) {}

    /**
     * @param  DatosBd  $bd
     * @param  DatosSistema  $sistema
     * @param  DatosAdmin  $admin
     */
    public function ejecutar(array $bd, array $sistema, array $admin, bool $demo): User
    {
        $this->conexion->configurar($bd);

        if (Artisan::call('migrate', ['--force' => true]) !== 0) {
            throw new RuntimeException('Las migraciones no terminaron: '.trim(Artisan::output()));
        }

        Auditoria::sinRegistrar(fn () => Artisan::call('db:seed', ['--class' => CatalogosSeeder::class, '--force' => true]));

        // El alta del primer administrador sí queda en la auditoría.
        $usuario = User::query()->create([
            'name' => $admin['nombre'],
            'email' => $admin['correo'],
            'password' => $admin['clave'],
            'rol' => RolUsuario::Admin,
            'activo' => true,
        ]);
        $usuario->forceFill(['email_verified_at' => now()])->save();

        if ($demo) {
            Auditoria::sinRegistrar(fn () => Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]));
        }

        $this->env->escribir([
            'APP_NAME' => $sistema['nombre'],
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => rtrim($sistema['url'], '/'),
            'LOG_LEVEL' => 'warning',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => $bd['host'],
            'DB_PORT' => (string) $bd['puerto'],
            'DB_DATABASE' => $bd['nombre'],
            'DB_USERNAME' => $bd['usuario'],
            'DB_PASSWORD' => $bd['clave'] ?? '',
            'SESSION_DRIVER' => 'database',
            'CACHE_STORE' => 'database',
            'QUEUE_CONNECTION' => 'database',
            ...$this->correo($sistema),
        ]);

        $this->instalacion->marcarInstalado((string) config('planillas.version', '1.0.0'));

        return $usuario;
    }

    /**
     * @param  DatosSistema  $sistema
     * @return array<string, string>
     */
    private function correo(array $sistema): array
    {
        if (blank($sistema['smtp_host'])) {
            // Sin SMTP los correos van al log; se configura después desde el .env.
            return ['MAIL_MAILER' => 'log'];
        }

        return [
            'MAIL_MAILER' => 'smtp',
            'MAIL_SCHEME' => $sistema['smtp_cifrado'] === 'ssl' ? 'smtps' : 'smtp',
            'MAIL_HOST' => (string) $sistema['smtp_host'],
            'MAIL_PORT' => (string) ($sistema['smtp_puerto'] ?? 587),
            'MAIL_USERNAME' => (string) $sistema['smtp_usuario'],
            'MAIL_PASSWORD' => (string) $sistema['smtp_clave'],
            'MAIL_FROM_ADDRESS' => (string) ($sistema['correo_remitente'] ?? $sistema['smtp_usuario']),
            'MAIL_FROM_NAME' => $sistema['nombre'],
        ];
    }
}
