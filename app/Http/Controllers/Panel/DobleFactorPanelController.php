<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Superadmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/** Configuración obligatoria del doble factor del superadmin (aplicación de autenticación). */
class DobleFactorPanelController extends Controller
{
    public function mostrar(Request $request, EnableTwoFactorAuthentication $activar): Response|RedirectResponse
    {
        $superadmin = $this->superadmin($request);

        if ($superadmin->tieneDosFactores()) {
            return to_route('panel.cuentas.index');
        }

        $activar($superadmin); // genera la clave solo si aún no existe

        return Inertia::render('panel/acceso/DobleFactor', [
            'qr' => $superadmin->twoFactorQrCodeSvg(),
            'clave' => Fortify::currentEncrypter()->decrypt((string) $superadmin->two_factor_secret),
        ]);
    }

    public function confirmar(Request $request, TwoFactorAuthenticationProvider $proveedor): Response|RedirectResponse
    {
        $superadmin = $this->superadmin($request);

        if ($superadmin->tieneDosFactores()) {
            return to_route('panel.cuentas.index');
        }

        $codigo = (string) $request->validate(['code' => ['required', 'string']])['code'];
        $secreto = Fortify::currentEncrypter()->decrypt((string) $superadmin->two_factor_secret);

        if (! $proveedor->verify($secreto, trim($codigo))) {
            throw ValidationException::withMessages(['code' => 'El código no es válido. Revisa la hora de tu teléfono e intenta de nuevo.']);
        }

        $superadmin->forceFill(['two_factor_confirmed_at' => now()])->save();
        AuditoriaCentral::registrar('doble_factor', "Doble factor activado para {$superadmin->email}");

        // Se muestran una sola vez.
        return Inertia::render('panel/acceso/CodigosRecuperacion', [
            'codigos' => $superadmin->recoveryCodes(),
        ]);
    }

    private function superadmin(Request $request): Superadmin
    {
        $superadmin = $request->user('superadmin');
        abort_unless($superadmin instanceof Superadmin, 403);

        return $superadmin;
    }
}
