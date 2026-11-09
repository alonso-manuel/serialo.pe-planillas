<?php

namespace App\Http\Middleware;

use App\Models\Concerns\PerteneceAEmpresa;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use Closure;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Si la URL apunta a un registro de otra empresa que el usuario puede ver (p. ej. un periodo abierto
     * desde un enlace), esa pasa a ser la empresa activa, para que el menú y los datos coincidan.
     * Si no puede verla, no se cambia nada y la policy responde 403.
     */
    private function seguirEmpresaDeLaRuta(Request $request, User $usuario): void
    {
        foreach ($request->route()?->parameters() ?? [] as $parametro) {
            if (! $parametro instanceof Model || ! in_array(PerteneceAEmpresa::class, class_uses_recursive($parametro), true)) {
                continue;
            }

            $empresaId = (int) $parametro->getAttribute('empresa_id');
            if ($empresaId !== $this->empresaActiva->obtener($usuario)?->id) {
                $empresa = $this->empresaActiva->seleccionables($usuario)->whereKey($empresaId)->first();
                if ($empresa !== null) {
                    $this->empresaActiva->cambiar($usuario, $empresa);
                }
            }

            return;
        }
    }

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

        if ($usuario instanceof User) {
            $this->seguirEmpresaDeLaRuta($request, $usuario);
        }

        return $next($request);
    }
}
