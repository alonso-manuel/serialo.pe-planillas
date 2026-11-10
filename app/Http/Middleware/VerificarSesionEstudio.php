<?php

namespace App\Http\Middleware;

use App\Support\ModoSistema;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo SaaS, defensa adicional: la sesión queda marcada con su estudio. Si llega a otro estudio
 * (p. ej. por una mala configuración de cookies o sesiones compartidas) se cierra y se pide ingresar
 * de nuevo, en vez de usar un usuario que podría coincidir por id en la otra base.
 */
class VerificarSesionEstudio
{
    public const CLAVE = 'estudio_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (! ModoSistema::saas() || ! tenancy()->initialized || ! $request->hasSession()) {
            return $next($request);
        }

        $estudio = (string) tenant()?->getTenantKey();
        $sesion = $request->session();
        $marcado = $sesion->get(self::CLAVE);

        if ($marcado !== null && $marcado !== $estudio) {
            Auth::guard('web')->logout();
            $sesion->invalidate();
            $sesion->regenerateToken();
        }

        $sesion->put(self::CLAVE, $estudio);

        return $next($request);
    }
}
