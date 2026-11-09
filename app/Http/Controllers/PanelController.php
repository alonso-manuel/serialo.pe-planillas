<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Panel\PanelEstudio;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Panel del estudio: estado del mes en cada empresa visible. */
class PanelController extends Controller
{
    public function __invoke(Request $request, PanelEstudio $panel): Response
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);
        /** @var User $usuario */
        $usuario = $request->user();

        // Por defecto, el mes actual (el que se está trabajando).
        return Inertia::render('Dashboard', $panel->datos(
            $usuario,
            (int) ($datos['anio'] ?? now()->year),
            (int) ($datos['mes'] ?? now()->month),
        ));
    }
}
