<?php

namespace App\Services\Empresas;

use App\Models\Empresa;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;

/**
 * Empresa con la que el usuario está trabajando, guardada en sesión.
 *
 * Solo se puede activar una empresa visible para el usuario y activa. Si la que está
 * en sesión deja de serlo (se quitó la asignación, se desactivó o se eliminó), se
 * activa la primera seleccionable por razón social. Registrado como `scoped`:
 * el valor se calcula una vez por request.
 */
class EmpresaActiva
{
    public const CLAVE_SESION = 'empresa_id';

    private ?Empresa $resuelta = null;

    private bool $yaResuelta = false;

    public function __construct(private readonly Session $session) {}

    /**
     * Empresas que el usuario puede elegir en el selector.
     *
     * @return Builder<Empresa>
     */
    public function seleccionables(User $user): Builder
    {
        return $user->empresasVisibles()
            ->where('activo', true)
            ->orderBy('razon_social');
    }

    public function obtener(User $user): ?Empresa
    {
        if ($this->yaResuelta) {
            return $this->resuelta;
        }

        $id = $this->session->get(self::CLAVE_SESION);

        $empresa = ($id !== null ? $this->seleccionables($user)->whereKey($id)->first() : null)
            ?? $this->seleccionables($user)->first();

        if ($empresa?->id !== $id) {
            $empresa === null
                ? $this->session->forget(self::CLAVE_SESION)
                : $this->session->put(self::CLAVE_SESION, $empresa->id);
        }

        $this->yaResuelta = true;

        return $this->resuelta = $empresa;
    }

    /** Requiere que la empresa ya esté validada como seleccionable (ver CambiarEmpresaActivaRequest). */
    public function cambiar(Empresa $empresa): void
    {
        $this->session->put(self::CLAVE_SESION, $empresa->id);
        $this->resuelta = $empresa;
        $this->yaResuelta = true;
    }
}
