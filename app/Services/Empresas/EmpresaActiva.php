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
 * el valor se calcula una vez por request y por usuario.
 */
class EmpresaActiva
{
    public const CLAVE_SESION = 'empresa_id';

    /** @var array<int, Empresa|null> empresa resuelta por id de usuario (memoria del request) */
    private array $resueltas = [];

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
        if (array_key_exists($user->id, $this->resueltas)) {
            return $this->resueltas[$user->id];
        }

        $id = $this->session->get(self::CLAVE_SESION);

        $empresa = ($id !== null ? $this->seleccionables($user)->whereKey($id)->first() : null)
            ?? $this->seleccionables($user)->first();

        if ($empresa?->id !== $id) {
            $empresa === null
                ? $this->session->forget(self::CLAVE_SESION)
                : $this->session->put(self::CLAVE_SESION, $empresa->id);
        }

        return $this->resueltas[$user->id] = $empresa;
    }

    /** Requiere que la empresa ya esté validada como seleccionable (ver CambiarEmpresaActivaRequest). */
    public function cambiar(User $user, Empresa $empresa): void
    {
        $this->session->put(self::CLAVE_SESION, $empresa->id);
        $this->resueltas[$user->id] = $empresa;
    }
}
