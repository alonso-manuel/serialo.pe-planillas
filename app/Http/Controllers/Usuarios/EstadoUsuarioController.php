<?php

namespace App\Http\Controllers\Usuarios;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Usuarios\GestionUsuarios;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EstadoUsuarioController extends Controller
{
    public function update(Request $request, User $usuario, GestionUsuarios $gestion): RedirectResponse
    {
        Gate::authorize('update', $usuario);

        /** @var User $actor */
        $actor = $request->user();

        try {
            $activo = $gestion->alternarEstado($usuario, $actor);
            Inertia::flash('toast', [
                'type' => 'success',
                'message' => $activo ? "{$usuario->name} puede volver a ingresar." : "{$usuario->name} ya no puede ingresar al sistema.",
            ]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
