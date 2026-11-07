<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Empresas\GestionEmpresas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class EstadoEmpresaController extends Controller
{
    public function update(Empresa $empresa, GestionEmpresas $gestion): RedirectResponse
    {
        Gate::authorize('update', $empresa);

        $activa = $gestion->alternarEstado($empresa);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $activa
                ? "{$empresa->razon_social} vuelve a estar disponible."
                : "{$empresa->razon_social} se desactivó y ya no aparece en el selector de empresas.",
        ]);

        return back();
    }
}
