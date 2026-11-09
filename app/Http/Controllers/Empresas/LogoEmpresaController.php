<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\Empresas\LogoEmpresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/** Logo de la empresa (solo administrador). */
class LogoEmpresaController extends Controller
{
    public function __construct(private readonly LogoEmpresa $logos) {}

    public function show(Empresa $empresa): Response
    {
        Gate::authorize('update', $empresa);

        $png = $this->logos->contenido($empresa) ?? abort(404);

        return response($png, 200, ['Content-Type' => 'image/png', 'Cache-Control' => 'private, max-age=300']);
    }

    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        Gate::authorize('update', $empresa);
        $request->validate(
            ['logo' => ['required', 'file', 'image', 'mimes:png,jpg,jpeg', 'max:1024']],
            ['logo.max' => 'El logo no puede pesar más de 1 MB.', 'logo.mimes' => 'El logo debe ser PNG o JPG.', 'logo.image' => 'El archivo no es una imagen.'],
            ['logo' => 'logo'],
        );

        try {
            $this->logos->guardar($empresa, $request->file('logo'));
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo actualizado: ya aparece en boletas, constancias y reportes.']);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        Gate::authorize('update', $empresa);

        $this->logos->quitar($empresa);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Logo quitado.']);

        return back();
    }
}
