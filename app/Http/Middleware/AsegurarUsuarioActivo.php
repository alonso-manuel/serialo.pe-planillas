<?php

namespace App\Http\Middleware;

use App\Models\PermisoSoporte;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si un administrador desactiva a un usuario que tiene la sesión abierta,
 * lo saca del sistema en su siguiente petición. Igual con la sesión de soporte cuando el estudio
 * quita el permiso o este vence.
 */
class AsegurarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user('web');

        if ($usuario instanceof User && ! $usuario->activo) {
            return $this->sacar($request, 'Tu cuenta fue desactivada. Contacta al administrador.');
        }

        if ($usuario instanceof User && $usuario->es_soporte && PermisoSoporte::vigente() === null) {
            return $this->sacar($request, 'El acceso de soporte terminó: el estudio lo quitó o venció.');
        }

        return $next($request);
    }

    private function sacar(Request $request, string $mensaje): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $mensaje);
    }
}
