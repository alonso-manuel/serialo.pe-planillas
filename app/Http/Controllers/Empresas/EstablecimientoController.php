<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Empresas\GuardarEstablecimientoRequest;
use App\Models\Empresa;
use App\Models\Establecimiento;
use App\Services\Empresas\GestionEmpresas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/** Establecimientos anidados en su empresa (rutas con scopeBindings). */
class EstablecimientoController extends Controller
{
    public function __construct(private readonly GestionEmpresas $gestion) {}

    public function store(GuardarEstablecimientoRequest $request, Empresa $empresa): RedirectResponse
    {
        /** @var array{codigo: string, nombre: string, direccion?: string|null} $datos */
        $datos = $request->validated();
        $this->gestion->agregarEstablecimiento($empresa, $datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Establecimiento agregado.']);

        return back();
    }

    public function update(GuardarEstablecimientoRequest $request, Empresa $empresa, Establecimiento $establecimiento): RedirectResponse
    {
        /** @var array{codigo: string, nombre: string, direccion?: string|null} $datos */
        $datos = $request->validated();
        $this->gestion->actualizarEstablecimiento($establecimiento, $datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Establecimiento actualizado.']);

        return back();
    }

    public function destroy(Empresa $empresa, Establecimiento $establecimiento): RedirectResponse
    {
        Gate::authorize('update', $empresa);

        try {
            $this->gestion->eliminarEstablecimiento($establecimiento);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Establecimiento eliminado.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
