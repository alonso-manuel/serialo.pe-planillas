<?php

namespace App\Http\Controllers\Empresas;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\User;
use App\Services\Empresas\FirmaDigitalPdf;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/** Certificado digital del representante legal para firmar boletas y documentos laborales (solo administrador). */
class FirmaDigitalController extends Controller
{
    public function __construct(private readonly FirmaDigitalPdf $firmas) {}

    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        Gate::authorize('update', $empresa);

        $request->validate(
            [
                'certificado' => ['required', 'file', 'extensions:pfx,p12', 'max:100'],
                'clave' => ['required', 'string', 'max:200'],
            ],
            [
                'certificado.extensions' => 'El certificado debe ser un archivo .pfx o .p12.',
                'certificado.max' => 'El certificado no puede pesar más de 100 KB.',
            ],
            ['certificado' => 'certificado digital', 'clave' => 'clave del certificado'],
        );

        /** @var User $usuario */
        $usuario = $request->user();
        $archivo = $request->file('certificado');
        $contenido = $archivo === null || is_array($archivo) ? '' : (string) file_get_contents($archivo->getRealPath());

        try {
            $firma = $this->firmas->registrar($empresa, $contenido, $request->string('clave')->toString(), $usuario);
        } catch (DomainException $e) {
            throw ValidationException::withMessages(['certificado' => $e->getMessage()]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Firma digital de {$firma->titular} registrada (vigente hasta el {$firma->valido_hasta->format('d/m/Y')}). Las boletas de periodos cerrados y los documentos del cese saldrán firmados.",
        ]);

        return back();
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        Gate::authorize('update', $empresa);

        $this->firmas->quitar($empresa);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Firma digital quitada: los documentos saldrán sin firma digital.']);

        return back();
    }
}
