<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Las rutas del panel central (panel.*) solo responden en su dominio, y las de los estudios nunca en el
 * dominio del panel. En modo «unica» el panel no existe.
 */
class SepararPanelYEstudios
{
    public function handle(Request $request, Closure $next): Response
    {
        $enPanel = $request->attributes->get(IdentificarEstudio::ATRIBUTO_PANEL) === true;

        abort_if($enPanel !== $request->routeIs('panel.*'), 404);

        return $next($request);
    }
}
