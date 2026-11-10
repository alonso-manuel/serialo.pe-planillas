<?php

namespace App\Http\Middleware;

use App\Models\Central\Superadmin;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** El superadmin no administra cuentas hasta configurar su doble factor. */
class ExigirDobleFactorPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        $superadmin = $request->user('superadmin');

        if ($superadmin instanceof Superadmin && ! $superadmin->tieneDosFactores()) {
            return to_route('panel.doble-factor');
        }

        return $next($request);
    }
}
