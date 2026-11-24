<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Central\Solicitud;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Panel central: solicitudes de cuenta de prueba que llegan desde la web pública (las más recientes primero). */
class SolicitudPanelController extends Controller
{
    public function index(): Response
    {
        $solicitudes = Solicitud::query()->latest('id')->limit(500)->get()
            ->map(fn (Solicitud $s) => [
                'id' => $s->id,
                'fecha' => $s->created_at->format('d/m/Y H:i'),
                'nombre' => $s->nombre,
                'tipo' => $s->tipo === 'empresa' ? 'Empresa' : 'Estudio contable',
                'organizacion' => $s->organizacion,
                'celular' => $s->celular,
                'whatsapp' => $s->whatsapp(),
                'correo' => $s->correo,
                'empresas' => $s->empresas,
                'mensaje' => $s->mensaje,
                'atendida' => $s->atendida_en !== null,
            ]);

        return Inertia::render('panel/Solicitudes', ['solicitudes' => $solicitudes]);
    }

    /** Marca (o desmarca) una solicitud como atendida. */
    public function atendida(Solicitud $solicitud): RedirectResponse
    {
        $solicitud->update(['atendida_en' => $solicitud->atendida_en === null ? now() : null]);

        return back(303);
    }
}
