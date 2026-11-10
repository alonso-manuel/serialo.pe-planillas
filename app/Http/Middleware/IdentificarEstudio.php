<?php

namespace App\Http\Middleware;

use App\Models\Central\Estudio;
use App\Support\ModoSistema;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Middleware\InitializeTenancyBySubdomain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Modo SaaS (middleware global): antes del enrutamiento decide el contexto de la petición.
 * - Dominio del panel (admin.serialo.pe): contexto central, con sus propias sesiones.
 * - Subdominio de un estudio (garcia.serialo.pe): su base; si está suspendido, aviso y nada más.
 * - Cualquier otro (desconocido o central): 404, salvo /up.
 * En modo «unica» no hace nada.
 */
class IdentificarEstudio
{
    public const ATRIBUTO_PANEL = 'panel_central';

    public function __construct(private readonly InitializeTenancyBySubdomain $porSubdominio) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! ModoSistema::saas() || $request->is('up')) {
            return $next($request);
        }

        if ($request->getHost() === config('planillas.dominio_panel')) {
            $request->attributes->set(self::ATRIBUTO_PANEL, true);
            config(['session.table' => 'sesiones_panel']);

            return $next($request);
        }

        $this->porSubdominio::$onFail = fn () => abort(404);

        return $this->porSubdominio->handle($request, function (Request $request) use ($next): Response {
            $estudio = tenant();

            if ($estudio instanceof Estudio && $estudio->suspendido()) {
                return response()->view('estudio-suspendido', ['estudio' => $estudio], 403);
            }

            return $next($request);
        });
    }
}
