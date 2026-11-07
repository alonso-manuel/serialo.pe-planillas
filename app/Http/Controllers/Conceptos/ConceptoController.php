<?php

namespace App\Http\Controllers\Conceptos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Conceptos\GuardarConceptoRequest;
use App\Models\Concepto;
use App\Services\Conceptos\GestionConceptos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ConceptoController extends Controller
{
    public function __construct(private readonly GestionConceptos $gestion) {}

    public function index(): Response
    {
        Gate::authorize('viewAny', Concepto::class);

        return Inertia::render('conceptos/Index', [
            'grupos' => $this->gestion->agrupados(),
        ]);
    }

    public function store(GuardarConceptoRequest $request): RedirectResponse
    {
        $concepto = $this->gestion->crear($request->string('codigo_plame')->toString(), $request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Concepto {$concepto->codigo_plame} registrado."]);

        return to_route('conceptos.index');
    }

    public function update(GuardarConceptoRequest $request, Concepto $concepto): RedirectResponse
    {
        $this->gestion->actualizar($concepto, $request->datos());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Concepto {$concepto->codigo_plame} actualizado."]);

        return to_route('conceptos.index');
    }

    public function destroy(Concepto $concepto): RedirectResponse
    {
        Gate::authorize('delete', $concepto);

        try {
            $this->gestion->eliminar($concepto);
            Inertia::flash('toast', ['type' => 'success', 'message' => "Concepto {$concepto->codigo_plame} eliminado."]);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }
}
