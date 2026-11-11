<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Models\CentroCosto;
use App\Models\Empresa;
use App\Models\PlanillaLinea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/** Centros de costo de la empresa (solo administrador). Uno usado en planillas no se borra: se desactiva. */
class CentroCostoController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        Gate::authorize('update', $empresa);
        $empresa->centrosCosto()->create($this->validar($request, $empresa));
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de costo agregado.']);

        return back();
    }

    public function update(Request $request, Empresa $empresa, CentroCosto $centro): RedirectResponse
    {
        Gate::authorize('update', $empresa);
        abort_unless($centro->empresa_id === $empresa->id, 404);

        $centro->update([...$this->validar($request, $empresa, $centro), 'activo' => $request->boolean('activo', true)]);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de costo actualizado.']);

        return back();
    }

    public function destroy(Empresa $empresa, CentroCosto $centro): RedirectResponse
    {
        Gate::authorize('update', $empresa);
        abort_unless($centro->empresa_id === $empresa->id, 404);

        if ($centro->trabajadores()->exists() || PlanillaLinea::query()->where('centro_costo_id', $centro->id)->exists()) {
            $centro->update(['activo' => false]);
            Inertia::flash('toast', ['type' => 'success', 'message' => 'El centro de costo tiene trabajadores o planillas: se desactivó en lugar de eliminarse.']);
        } else {
            $centro->delete();
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Centro de costo eliminado.']);
        }

        return back();
    }

    /** @return array{codigo: string, nombre: string} */
    private function validar(Request $request, Empresa $empresa, ?CentroCosto $centro = null): array
    {
        $request->merge(['codigo' => mb_strtoupper(trim($request->string('codigo')->toString()))]);

        /** @var array{codigo: string, nombre: string} */
        return $request->validate([
            'codigo' => ['required', 'string', 'max:10', 'regex:/^[A-Z0-9.\-]+$/', Rule::unique('centros_costo')->where('empresa_id', $empresa->id)->ignore($centro?->id)],
            'nombre' => ['required', 'string', 'max:100'],
        ], [
            'codigo.regex' => 'El código admite letras, dígitos, punto y guion.',
            'codigo.unique' => 'Ya existe un centro de costo con ese código.',
        ], ['codigo' => 'código', 'nombre' => 'nombre']);
    }
}
