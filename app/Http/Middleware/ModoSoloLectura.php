<?php

namespace App\Http\Middleware;

use App\Services\Licencia\LicenciaActual;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sin licencia válida (prueba vencida, clave inválida u otro dominio) el sistema queda en solo lectura:
 * se puede consultar y descargar todo, pero no guardar cambios. Nunca se borra nada.
 * Siguen permitidos el ingreso y la salida, y la pantalla para registrar la licencia.
 */
class ModoSoloLectura
{
    private const PERMITIDAS = [
        'logout',
        'password.confirm.store',
        'two-factor.login.store',
        'passkey.*',
        'licencia.update',
    ];

    public function __construct(private readonly LicenciaActual $licencia) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || $request->user() === null || $request->routeIs(...self::PERMITIDAS)) {
            return $next($request);
        }

        $estado = $this->licencia->estado($request->getHost());

        if (! $estado->soloLectura()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => $estado->mensaje], 423);
        }

        Inertia::flash('toast', ['type' => 'error', 'message' => $estado->mensaje]);

        return back(303);
    }
}
