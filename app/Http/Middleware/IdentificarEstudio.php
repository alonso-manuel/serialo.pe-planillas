<?php

namespace App\Http\Middleware;

use App\Support\ModoSistema;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo SaaS (middleware global): elige el estudio por el subdominio (garcia.serialo.pe → base de «garcia»)
 * antes del enrutamiento, así sesión, usuarios, caché, archivos y cola son siempre los de ese estudio.
 * Un subdominio desconocido o un dominio central (sin panel todavía) responde 404, salvo /up.
 * En modo «unica» no hace nada.
 */
class IdentificarEstudio
{
    public function __construct(private readonly InitializeTenancyBySubdomain $porSubdominio) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! ModoSistema::saas() || $request->is('up')) {
            return $next($request);
        }

        $this->porSubdominio::$onFail = fn () => abort(404);

        return $this->porSubdominio->handle($request, $next);
    }
}
