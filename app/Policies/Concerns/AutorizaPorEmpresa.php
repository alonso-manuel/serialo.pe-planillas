<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Autorización estándar para modelos con `empresa_id`: el usuario solo actúa
 * sobre registros de empresas que puede ver (admin: todas; asistente: asignadas).
 * Las policies que lo usan pueden sobrescribir un método para agregar reglas.
 */
trait AutorizaPorEmpresa
{
    public function viewAny(User $user): bool
    {
        return true; // el listado se filtra con deEmpresa()/visiblesPara()
    }

    public function view(User $user, Model $modelo): bool
    {
        return $this->mismaEmpresa($user, $modelo);
    }

    public function create(User $user): bool
    {
        return true; // se crea siempre en la empresa activa, que ya es visible
    }

    public function update(User $user, Model $modelo): bool
    {
        return $this->mismaEmpresa($user, $modelo);
    }

    public function delete(User $user, Model $modelo): bool
    {
        return $this->mismaEmpresa($user, $modelo);
    }

    protected function mismaEmpresa(User $user, Model $modelo): bool
    {
        $empresaId = $modelo->getAttribute('empresa_id');

        return is_int($empresaId) && $user->puedeVerEmpresaId($empresaId);
    }
}
