<?php

namespace App\Http\Controllers\Trabajadores;

use App\Enums\MotivoCese;
use App\Http\Controllers\Controller;
use App\Models\Trabajador;
use App\Services\Trabajadores\CeseTrabajador;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CeseTrabajadorController extends Controller
{
    public function __construct(private readonly CeseTrabajador $cese) {}

    public function store(Request $request, Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        $datos = $request->validate(
            [
                'fecha_cese' => ['required', 'date_format:Y-m-d'],
                'motivo_cese' => ['required', Rule::enum(MotivoCese::class)],
            ],
            [],
            ['fecha_cese' => 'fecha de cese', 'motivo_cese' => 'motivo de cese'],
        );

        $fecha = CarbonImmutable::createFromFormat('Y-m-d', $datos['fecha_cese'])->startOfDay();
        $this->cese->registrar($trabajador, $fecha, MotivoCese::from($datos['motivo_cese']));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Cese de {$trabajador->nombre_completo} registrado al ".$fecha->format('d/m/Y').'.',
        ]);

        return back();
    }

    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        Gate::authorize('update', $trabajador);

        $this->cese->revertir($trabajador);
        Inertia::flash('toast', ['type' => 'success', 'message' => "Se revirtió el cese de {$trabajador->nombre_completo}."]);

        return back();
    }
}
