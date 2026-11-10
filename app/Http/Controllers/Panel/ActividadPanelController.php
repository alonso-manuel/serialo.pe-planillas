<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Central\AuditoriaCentral;
use Inertia\Inertia;
use Inertia\Response;

/** Registro de lo que hizo el superadmin en el panel (últimas 200 acciones). */
class ActividadPanelController extends Controller
{
    private const ACCIONES = [
        'ingreso' => 'Ingreso', 'doble_factor' => 'Doble factor', 'creado' => 'Cuenta creada',
        'invitado' => 'Enlace de acceso', 'suspendido' => 'Suspendida', 'reactivado' => 'Reactivada',
        'eliminado' => 'Eliminada', 'actualizado' => 'Cuenta editada', 'aviso' => 'Aviso por correo',
    ];

    public function __invoke(): Response
    {
        $registros = AuditoriaCentral::query()->with('superadmin:id,name')
            ->orderByDesc('id')->limit(200)->get()
            ->map(fn (AuditoriaCentral $a) => [
                'id' => $a->id,
                'fecha' => $a->created_at->format('d/m/Y H:i'),
                'accion' => self::ACCIONES[$a->accion] ?? $a->accion,
                'descripcion' => $a->descripcion,
                'cuenta' => $a->estudio_id,
                'quien' => $a->superadmin?->name,
                'ip' => $a->ip,
            ]);

        return Inertia::render('panel/Actividad', ['registros' => $registros]);
    }
}
