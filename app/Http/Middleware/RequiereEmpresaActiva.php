<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Para las rutas que trabajan dentro de una empresa (trabajadores, planilla...).
 * Si el usuario no tiene ninguna empresa activa disponible, vuelve al panel con un aviso.
 * Alias: `empresa.activa`.
 */
class RequiereEmpresaActiva
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    public function handle(Request $request, Closure $next): Response
    {
        $usuario = $request->user();

        if ($usuario instanceof User && $this->empresaActiva->obtener($usuario) === null) {
            Inertia::flash('toast', [
                'type' => 'warning',
                'message' => $usuario->esAdmin()
                    ? 'Primero registra una empresa activa.'
                    : 'No tienes empresas asignadas. Pide al administrador que te asigne una.',
            ]);

            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}
