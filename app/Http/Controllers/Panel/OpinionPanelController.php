<?php

namespace App\Http\Controllers\Panel;

use App\Enums\RolUsuario;
use App\Http\Controllers\Controller;
use App\Models\Central\Opinion;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** Panel central: comentarios que los usuarios envían sobre el sistema (los más recientes primero). */
class OpinionPanelController extends Controller
{
    public function index(): Response
    {
        $opiniones = Opinion::query()->latest('id')->limit(500)->get()
            ->map(fn (Opinion $o) => [
                'id' => $o->id,
                'fecha' => $o->created_at->format('d/m/Y H:i'),
                'cuenta' => $o->estudio_nombre,
                'subdominio' => $o->estudio_id,
                'usuario' => $o->usuario_nombre,
                'correo' => $o->usuario_correo,
                'rol' => RolUsuario::tryFrom($o->usuario_rol)?->etiqueta() ?? $o->usuario_rol,
                'calificacion' => $o->calificacion,
                'comentario' => $o->comentario,
                'leida' => $o->leida_en !== null,
            ]);

        return Inertia::render('panel/Opiniones', [
            'opiniones' => $opiniones,
            'promedio' => ($p = Opinion::query()->avg('calificacion')) === null ? null : round((float) $p, 1),
        ]);
    }

    /** Marca (o desmarca) un comentario como leído. */
    public function leida(Opinion $opinion): RedirectResponse
    {
        $opinion->update(['leida_en' => $opinion->leida_en === null ? now() : null]);

        return back(303);
    }
}
