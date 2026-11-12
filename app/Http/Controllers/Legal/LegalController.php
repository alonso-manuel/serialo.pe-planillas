<?php

namespace App\Http\Controllers\Legal;

use App\Http\Controllers\Controller;
use App\Models\Central\Estudio;
use App\Support\ModoSistema;
use Inertia\Inertia;
use Inertia\Response;

/** Términos del servicio y política de privacidad: páginas públicas, con los datos del responsable de esta instalación. */
class LegalController extends Controller
{
    public function terminos(): Response
    {
        return Inertia::render('legal/Terminos', ['legal' => $this->datos()]);
    }

    public function privacidad(): Response
    {
        return Inertia::render('legal/Privacidad', ['legal' => $this->datos()]);
    }

    /** @return array{version: string, responsable: string|null, correo: string|null, saas: bool} */
    private function datos(): array
    {
        $responsable = config('planillas.legal.responsable');
        if (blank($responsable) && ModoSistema::saas() && tenancy()->initialized && tenant() instanceof Estudio) {
            $responsable = tenant()->nombre;
        }

        return [
            'version' => (string) config('planillas.legal.version'),
            'responsable' => filled($responsable) ? (string) $responsable : null,
            'correo' => filled(config('planillas.legal.correo')) ? (string) config('planillas.legal.correo') : null,
            'saas' => ModoSistema::saas(),
        ];
    }
}
