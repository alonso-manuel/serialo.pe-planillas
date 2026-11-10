<?php

namespace App\Http\Controllers\Soporte;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Soporte\AccesoSoporte;
use App\Support\ModoSistema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

/** Acceso de soporte (modo SaaS): el admin del estudio lo autoriza o lo quita; el enlace del panel inicia la sesión. */
class SoporteController extends Controller
{
    public function __construct(private readonly AccesoSoporte $soporte) {}

    public function autorizar(Request $request): RedirectResponse
    {
        $admin = $this->adminDelEstudio($request);
        $permiso = $this->soporte->autorizar($admin);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Serialo.pe puede entrar como soporte hasta el '.$permiso->vence_en->format('d/m/Y H:i').'.']);

        return back();
    }

    public function revocar(Request $request): RedirectResponse
    {
        $this->adminDelEstudio($request);
        $this->soporte->revocar();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Se quitó el acceso de soporte. Si había una sesión abierta, se cierra.']);

        return back();
    }

    /** Enlace de un solo uso generado desde el panel central. */
    public function entrar(Request $request, string $token): RedirectResponse
    {
        abort_unless(ModoSistema::saas(), 404);

        $usuario = $this->soporte->canjear($token);
        if ($usuario === null) {
            return redirect()->route('login')->with('status', 'El enlace de soporte no es válido o ya venció. Genera otro desde el panel.');
        }

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
        }
        Auth::guard('web')->login($usuario);
        $request->session()->regenerate();
        $this->soporte->registrarIngreso();

        return redirect()->route('dashboard');
    }

    private function adminDelEstudio(Request $request): User
    {
        abort_unless(ModoSistema::saas(), 404);

        /** @var User $usuario */
        $usuario = $request->user();
        abort_unless($usuario->esAdmin() && ! $usuario->es_soporte, 403); // soporte no se autoriza a sí mismo

        return $usuario;
    }
}
