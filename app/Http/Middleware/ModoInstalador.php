<?php

namespace App\Http\Middleware;

use App\Services\Instalador\ArchivoEnv;
use App\Services\Instalador\Instalacion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras el sistema no está instalado, toda la web lleva al instalador; una vez instalado,
 * el instalador deja de existir (404). Va antes que la sesión: sin base de datos todavía,
 * la sesión y la caché usan archivos.
 */
class ModoInstalador
{
    public function __construct(
        private readonly Instalacion $instalacion,
        private readonly ArchivoEnv $env,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $esInstalador = $request->routeIs('instalar.*');

        if ($this->instalacion->instalado()) {
            abort_if($esInstalador, 404);

            return $next($request);
        }

        $this->env->asegurarClave();
        $this->instalacion->asegurarCodigo();

        if (config('session.driver') === 'database') {
            config(['session.driver' => 'file']);
        }

        if (config('cache.default') === 'database') {
            config(['cache.default' => 'file']);
        }

        if (! $esInstalador) {
            return redirect()->route('instalar.inicio');
        }

        return $next($request);
    }
}
