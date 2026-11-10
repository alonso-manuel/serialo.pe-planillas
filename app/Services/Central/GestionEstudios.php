<?php

namespace App\Services\Central;

use App\Enums\EstadoEstudio;
use App\Enums\RolUsuario;
use App\Enums\TipoEstudio;
use App\Models\Auditoria;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Estudio;
use App\Models\User;
use App\Notifications\InvitacionEstudio;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Throwable;

/**
 * Cuentas del modo SaaS (estudios): alta con su base limpia, invitación al administrador,
 * suspensión y eliminación. Lo usan el panel central y el comando estudio:crear.
 */
class GestionEstudios
{
    /** Subdominios que no pueden ser estudios. */
    public const RESERVADOS = ['www', 'admin', 'app', 'api', 'mail', 'smtp', 'ftp', 'cpanel', 'webmail', 'soporte', 'static', 'assets', 'demo', 'blog', 'panel'];

    /** @return list<mixed> */
    public static function reglasSubdominio(): array
    {
        return ['required', 'string', 'regex:/^[a-z][a-z0-9-]{1,28}[a-z0-9]$/', 'not_in:'.implode(',', self::RESERVADOS)];
    }

    /** @return array<string, string> */
    public static function mensajesSubdominio(): array
    {
        return [
            'subdominio.regex' => 'El subdominio debe tener de 3 a 30 caracteres: minúsculas, números y guiones, empezando con letra.',
            'subdominio.not_in' => 'Ese subdominio está reservado.',
            'subdominio.unique' => 'Ya existe una cuenta con ese subdominio.',
        ];
    }

    /**
     * Crea la cuenta: su base (migraciones y catálogos legales) y su administrador con una contraseña
     * aleatoria que nadie conoce (se crea la propia con la invitación) o la indicada.
     */
    public function crear(
        string $subdominio,
        string $nombre,
        string $correo,
        string $nombreAdmin,
        TipoEstudio $tipo = TipoEstudio::Cliente,
        ?CarbonImmutable $venceEn = null,
        ?string $clave = null,
    ): Estudio {
        $estudio = null;

        try {
            // Base, migraciones y catálogos legales (carga inicial: sin auditoría, como en el instalador).
            $estudio = Auditoria::sinRegistrar(fn () => Estudio::create([
                'id' => $subdominio,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'vence_en' => $venceEn?->toDateString(),
                'correo_admin' => Str::lower($correo),
            ]));
            $estudio->domains()->create(['domain' => $subdominio]);

            $estudio->run(function () use ($correo, $nombreAdmin, $clave): void {
                $admin = User::query()->create([
                    'name' => $nombreAdmin,
                    'email' => Str::lower($correo),
                    'password' => $clave ?? Str::password(32),
                    'rol' => RolUsuario::Admin,
                    'activo' => true,
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            });
        } catch (Throwable $e) {
            $estudio?->delete(); // también borra su base

            throw $e;
        }

        AuditoriaCentral::registrar('creado', "Cuenta {$nombre}", $estudio->id, [
            'tipo' => $tipo->value, 'correo_admin' => Str::lower($correo), 'vence_en' => $venceEn?->toDateString(),
        ]);

        return $estudio;
    }

    /**
     * Envía al administrador del estudio el enlace para crear (o restablecer) su contraseña.
     * Devuelve el enlace si el correo no se pudo enviar o no está configurado, para entregarlo a mano.
     */
    public function invitar(Estudio $estudio): ?string
    {
        $enlace = $estudio->run(function () use ($estudio): string {
            $admin = User::query()->where('email', $estudio->correo_admin)->first()
                ?? User::query()->where('rol', RolUsuario::Admin)->orderBy('id')->firstOrFail();

            // Broker nuevo: el del contenedor guarda la conexión de la primera base en que se usó.
            $token = (new PasswordBrokerManager(app()))->broker()->createToken($admin);

            // El enlace debe apuntar al subdominio del estudio, no al panel.
            URL::forceRootUrl($estudio->url());
            try {
                return route('password.reset', ['token' => $token, 'email' => $admin->email]);
            } finally {
                URL::forceRootUrl(null);
            }
        });

        $sinCorreo = in_array(config('mail.default'), ['log', 'array'], true);

        try {
            $estudio->run(function () use ($estudio, $enlace): void {
                User::query()->where('email', $estudio->correo_admin)->first()
                    ?->notify(new InvitacionEstudio($estudio->nombre, $estudio->url(), $enlace));
            });
        } catch (Throwable $e) {
            report($e);
            $sinCorreo = true;
        }

        AuditoriaCentral::registrar('invitado', "Enlace de acceso para {$estudio->correo_admin}", $estudio->id);

        return $sinCorreo ? $enlace : null;
    }

    public function cambiarEstado(Estudio $estudio, EstadoEstudio $estado): void
    {
        $estudio->update([
            'estado' => $estado,
            'suspendido_en' => $estado === EstadoEstudio::Suspendido ? now() : null,
        ]);

        AuditoriaCentral::registrar(
            $estado === EstadoEstudio::Suspendido ? 'suspendido' : 'reactivado',
            "Cuenta {$estudio->nombre}",
            $estudio->id,
        );
    }

    /** Borra la cuenta, su base y sus archivos. No se puede deshacer. */
    public function eliminar(Estudio $estudio): void
    {
        $id = $estudio->id;
        $nombre = $estudio->nombre;

        $estudio->delete();
        File::deleteDirectory(storage_path('estudio-'.$id));

        AuditoriaCentral::registrar('eliminado', "Cuenta {$nombre}", $id);
    }
}
