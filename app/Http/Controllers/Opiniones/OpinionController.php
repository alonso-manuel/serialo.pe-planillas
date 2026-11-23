<?php

namespace App\Http\Controllers\Opiniones;

use App\Http\Controllers\Controller;
use App\Http\Requests\Opiniones\GuardarOpinionRequest;
use App\Models\User;
use App\Services\Opiniones\PedidoOpinion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/** Modo SaaS: el usuario envía su comentario sobre el sistema (se lee en el panel central). */
class OpinionController extends Controller
{
    public function __construct(private readonly PedidoOpinion $pedido) {}

    /** La ventana se abrió sola: se marca para que no vuelva a aparecer. */
    public function mostrada(Request $request): RedirectResponse
    {
        $usuario = $request->user();
        abort_unless($usuario instanceof User && $this->pedido->habilitada($usuario), 404);

        $this->pedido->marcarMostrada($usuario);

        return back(303);
    }

    public function store(GuardarOpinionRequest $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $datos = $request->validated();

        $this->pedido->guardar($usuario, trim((string) $datos['comentario']), isset($datos['calificacion']) ? (int) $datos['calificacion'] : null);

        Inertia::flash('toast', ['type' => 'success', 'message' => '¡Gracias por tu comentario! Lo leeré con atención.']);

        return back(303);
    }
}
