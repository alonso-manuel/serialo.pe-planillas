<?php

namespace App\Services\Usuarios;

use App\Enums\RolUsuario;
use App\Models\User;
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
    /**
     * @param  array{name: string, email: string, rol: string, password: string, empresas?: array<int, int>}  $datos
     */
    public function crear(array $datos): User
    {
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
            $usuario->save();

            $usuario->empresas()->sync($this->empresasPara($rol, $datos));

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

            $usuario->save();
            $usuario->empresas()->sync($this->empresasPara($rol, $datos));

            return $usuario;
        });
    }

    /** Activa o desactiva. Devuelve el nuevo estado. */
    public function alternarEstado(User $usuario, User $actor): bool
    {
        if ($usuario->is($actor)) {
            throw new \DomainException('No puedes desactivar tu propia cuenta.');
        }

        $usuario->activo = ! $usuario->activo;
        $usuario->save();

        return $usuario->activo;
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
