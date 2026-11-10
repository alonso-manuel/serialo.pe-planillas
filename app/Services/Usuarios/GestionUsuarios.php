<?php

namespace App\Services\Usuarios;

use App\Domain\Licencia\Recurso;
use App\Enums\RolUsuario;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Licencia\LicenciaActual;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y activación de usuarios del estudio.
 *
 * Reglas que protegen el acceso al sistema:
 * - Un admin no puede quitarse el rol ni desactivarse a sí mismo. Como quien
 *   gestiona usuarios siempre es un admin activo, el sistema nunca queda sin uno.
 * - Los usuarios creados por el admin quedan con el correo verificado (el hosting
 *   puede no tener correo configurado).
 * - Un admin ve todas las empresas: no guarda asignaciones en empresa_user.
 */
class GestionUsuarios
{
    public function __construct(private readonly LicenciaActual $licencia) {}

    /**
     * @param  array{name: string, email: string, rol: string, password: string, empresas?: array<int, int>}  $datos
     */
    public function crear(array $datos): User
    {
        $this->licencia->asegurarCupo(Recurso::Usuarios);

        return DB::transaction(function () use ($datos) {
            $rol = RolUsuario::from($datos['rol']);

            $usuario = new User([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'password' => $datos['password'],
                'rol' => $rol,
                'activo' => true,
            ]);
            $usuario->email_verified_at = now();
            $empresas = $this->empresasPara($rol, $datos);
            $usuario->conCambiosAuditables(['empresas' => [[], $this->nombresDeEmpresas($empresas)]]);
            $usuario->save();

            $usuario->empresas()->sync($empresas);

            return $usuario;
        });
    }

    /**
     * @param  array{name: string, email: string, rol: string, password?: string|null, empresas?: array<int, int>}  $datos
     */
    public function actualizar(User $usuario, array $datos, User $actor): User
    {
        $rol = RolUsuario::from($datos['rol']);

        if ($usuario->is($actor) && $rol !== RolUsuario::Admin) {
            throw ValidationException::withMessages([
                'rol' => 'No puedes quitarte el rol de administrador a ti mismo.',
            ]);
        }

        return DB::transaction(function () use ($usuario, $datos, $rol) {
            $usuario->fill([
                'name' => $datos['name'],
                'email' => $datos['email'],
                'rol' => $rol,
            ]);

            if (filled($datos['password'] ?? null)) {
                $usuario->password = $datos['password'];
            }

            $empresas = $this->empresasPara($rol, $datos);
            $usuario->conCambiosAuditables(['empresas' => [
                $this->nombresDeEmpresas($usuario->empresas()->pluck('empresas.id')->all()),
                $this->nombresDeEmpresas($empresas),
            ]]);

            $usuario->save();
            $usuario->registrarCambiosPendientes(); // si solo cambiaron las empresas asignadas
            $usuario->empresas()->sync($empresas);

            return $usuario;
        });
    }

    /** Activa o desactiva. Devuelve el nuevo estado. */
    public function alternarEstado(User $usuario, User $actor): bool
    {
        if ($usuario->is($actor)) {
            throw new \DomainException('No puedes desactivar tu propia cuenta.');
        }

        if (! $usuario->activo) {
            $this->licencia->asegurarCupo(Recurso::Usuarios);
        }

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        return $usuario->activo;
    }

    /**
     * Razones sociales ordenadas (legibles en la auditoría aun si cambian los ids).
     *
     * @param  array<int, int>  $ids
     * @return array<int, string>
     */
    private function nombresDeEmpresas(array $ids): array
    {
        return $ids === [] ? [] : Empresa::query()
            ->whereKey($ids)
            ->orderBy('razon_social')
            ->pluck('razon_social')
            ->map(fn (mixed $r) => (string) $r)
            ->values()
            ->all();
    }

    /**
     * @param  array{empresas?: array<int, int>}  $datos
     * @return array<int, int>
     */
    private function empresasPara(RolUsuario $rol, array $datos): array
    {
        return $rol === RolUsuario::Admin ? [] : array_values($datos['empresas'] ?? []);
    }
}
