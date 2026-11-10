<?php

namespace App\Services\Soporte;

use App\Enums\RolUsuario;
use App\Models\Auditoria;
use App\Models\PermisoSoporte;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Acceso de soporte de Serialo.pe a un estudio (modo SaaS), siempre autorizado por el estudio:
 *
 * 1. El admin del estudio lo autoriza por HORAS horas (o lo quita cuando quiera).
 * 2. El superadmin pulsa «Soporte» en el panel: se genera un enlace de un solo uso que vale un minuto.
 * 3. El enlace inicia sesión como el usuario «Soporte Serialo.pe» (rol admin, no cuenta para el plan,
 *    no puede entrar con contraseña). Todo lo que haga queda en la auditoría a su nombre.
 * 4. Al vencer o quitarse el permiso, la sesión de soporte se cierra en la siguiente petición.
 */
class AccesoSoporte
{
    public const HORAS = 24;

    public const CORREO = 'soporte@serialo.pe';

    private const SEGUNDOS_ENLACE = 60;

    public function autorizar(User $admin): PermisoSoporte
    {
        return DB::transaction(function () use ($admin) {
            PermisoSoporte::query()->whereNull('revocado_en')->update(['revocado_en' => now(), 'token_hash' => null]);

            $permiso = PermisoSoporte::query()->create(['autorizado_por' => $admin->id, 'vence_en' => now()->addHours(self::HORAS)]);
            $this->auditar('autorizado', 'Acceso de soporte autorizado hasta el '.$permiso->vence_en->format('d/m/Y H:i'));

            return $permiso;
        });
    }

    public function revocar(): void
    {
        if (PermisoSoporte::query()->whereNull('revocado_en')->update(['revocado_en' => now(), 'token_hash' => null]) > 0) {
            $this->auditar('revocado', 'Acceso de soporte retirado');
        }
    }

    /** Token del enlace de ingreso; null si el estudio no autorizó el acceso. */
    public function prepararIngreso(): ?string
    {
        $permiso = PermisoSoporte::vigente();
        if ($permiso === null) {
            return null;
        }

        $token = Str::random(64);
        $permiso->update(['token_hash' => hash('sha256', $token), 'token_vence' => now()->addSeconds(self::SEGUNDOS_ENLACE)]);

        return $token;
    }

    /** Valida y consume el enlace; devuelve el usuario de soporte (sin iniciar sesión) o null. */
    public function canjear(string $token): ?User
    {
        return DB::transaction(function () use ($token) {
            $permiso = PermisoSoporte::vigente();
            if ($permiso === null || $permiso->token_hash === null || $permiso->token_vence === null
                || ! hash_equals($permiso->token_hash, hash('sha256', $token)) || $permiso->token_vence->isPast()) {
                return null;
            }

            $permiso->update(['token_hash' => null, 'token_vence' => null]); // un solo uso

            return $this->usuario();
        });
    }

    public function registrarIngreso(): void
    {
        $this->auditar('ingreso', 'Ingreso de Soporte Serialo.pe');
    }

    /** El usuario de soporte del estudio (se crea la primera vez). Su contraseña es aleatoria y nadie la conoce. */
    public function usuario(): User
    {
        $usuario = User::query()->where('es_soporte', true)->first();

        if (! $usuario instanceof User) {
            $usuario = Auditoria::sinRegistrar(fn () => User::query()->forceCreate([
                'name' => 'Soporte Serialo.pe',
                'email' => self::CORREO,
                'password' => Str::random(64),
                'rol' => RolUsuario::Admin,
                'activo' => true,
                'es_soporte' => true,
                'email_verified_at' => now(),
            ]));
        }

        return $usuario;
    }

    private function auditar(string $accion, string $descripcion): void
    {
        Auditoria::query()->create([
            'user_id' => Auth::guard('web')->id(),
            'empresa_id' => null,
            'auditable_type' => 'soporte',
            'auditable_id' => 0,
            'descripcion' => $descripcion,
            'accion' => $accion,
            'cambios' => null,
            'ip' => app()->bound('request') ? request()->ip() : null,
        ]);
    }
}
