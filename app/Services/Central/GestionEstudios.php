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
use App\Services\Respaldo\VolcadoBaseDatos;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Passwords\PasswordBrokerManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
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

    /** Carpeta (disco local central) del último respaldo de cada cuenta eliminada. */
    public const CARPETA_ELIMINADAS = 'respaldos/eliminadas';

    public function __construct(private readonly VolcadoBaseDatos $volcado) {}

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
     *
     * @param  array{nombre: string, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null}|null  $plan  null = plantilla del tipo
     */
    public function crear(
        string $subdominio,
        string $nombre,
        string $correo,
        string $nombreAdmin,
        TipoEstudio $tipo = TipoEstudio::Cliente,
        ?CarbonImmutable $venceEn = null,
        ?string $clave = null,
        ?array $plan = null,
    ): Estudio {
        $estudio = null;
        $plan ??= self::planPorDefecto($tipo);

        try {
            // Base, migraciones y catálogos legales (carga inicial: sin auditoría, como en el instalador).
            $estudio = Auditoria::sinRegistrar(fn () => Estudio::create([
                'id' => $subdominio,
                'nombre' => $nombre,
                'tipo' => $tipo,
                'vence_en' => $venceEn?->toDateString(),
                'correo_admin' => Str::lower($correo),
                'plan' => $plan['nombre'],
                'max_empresas' => $plan['max_empresas'],
                'max_usuarios' => $plan['max_usuarios'],
                'max_trabajadores' => $plan['max_trabajadores'],
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
            'tipo' => $tipo->value, 'plan' => $plan['nombre'], 'correo_admin' => Str::lower($correo), 'vence_en' => $venceEn?->toDateString(),
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

    /**
     * Plantilla de plan (config planillas.planes): demo para las demos, «Estudio» para los clientes.
     *
     * @return array{nombre: string, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null}
     */
    public static function planPorDefecto(TipoEstudio $tipo): array
    {
        /** @var array{nombre: string, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null} $plan */
        $plan = config('planillas.planes.'.($tipo === TipoEstudio::Demo ? 'demo' : 'estudio'));

        return [
            'nombre' => $plan['nombre'],
            'max_empresas' => $plan['max_empresas'],
            'max_usuarios' => $plan['max_usuarios'],
            'max_trabajadores' => $plan['max_trabajadores'],
        ];
    }

    /**
     * Cambia nombre, tipo, plan, límites o vencimiento. Si cambia el vencimiento, se vuelve a avisar por correo.
     *
     * @param  array{nombre: string, tipo: string, plan: string, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null, vence_en: string|null}  $datos
     */
    public function actualizar(Estudio $estudio, array $datos): void
    {
        $antes = $estudio->only(['nombre', 'tipo', 'plan', 'max_empresas', 'max_usuarios', 'max_trabajadores', 'vence_en']);
        $antes['tipo'] = $estudio->tipo->value;
        $antes['vence_en'] = $estudio->vence_en?->toDateString();

        $estudio->fill([
            'nombre' => $datos['nombre'],
            'tipo' => TipoEstudio::from($datos['tipo']),
            'plan' => $datos['plan'],
            'max_empresas' => $datos['max_empresas'],
            'max_usuarios' => $datos['max_usuarios'],
            'max_trabajadores' => $datos['max_trabajadores'],
            'vence_en' => $datos['vence_en'],
        ]);
        if ($antes['vence_en'] !== $datos['vence_en']) {
            $estudio->aviso_vencimiento = null;
        }
        $estudio->save();

        $cambios = [];
        foreach ($antes as $campo => $valor) {
            $nuevo = $datos[$campo];
            if ((string) $valor !== (string) $nuevo) {
                $cambios[$campo] = [$valor, $nuevo];
            }
        }
        if ($cambios !== []) {
            AuditoriaCentral::registrar('actualizado', "Cuenta {$estudio->nombre}", $estudio->id, $cambios);
        }
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

        // Último respaldo antes de borrar: si falla, la cuenta no se elimina.
        $respaldo = $this->volcarA($estudio, Storage::disk('local')->path(self::CARPETA_ELIMINADAS));

        $estudio->delete();
        File::deleteDirectory(storage_path('estudio-'.$id));

        AuditoriaCentral::registrar('eliminado', "Cuenta {$nombre}", $id, ['respaldo' => basename($respaldo)]);
    }

    /** Base completa de la cuenta en un .sql.gz temporal (para descargar desde el panel). */
    public function exportar(Estudio $estudio): string
    {
        $ruta = $this->volcarA($estudio, Storage::disk('local')->path('respaldos/exportaciones'));

        AuditoriaCentral::registrar('exportado', "Base de {$estudio->nombre}", $estudio->id, ['archivo' => basename($ruta)]);

        return $ruta;
    }

    private function volcarA(Estudio $estudio, string $carpeta): string
    {
        File::ensureDirectoryExists($carpeta);
        $ruta = $carpeta.'/'.$estudio->id.'-'.CarbonImmutable::now('America/Lima')->format('Ymd-His').'.sql.gz';

        $estudio->run(fn () => $this->volcado->volcar(DB::connection(), $ruta));

        return $ruta;
    }
}
