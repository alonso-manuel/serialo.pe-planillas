<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Models\Central\AuditoriaCentral;
use App\Models\Central\Superadmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Ingreso del superadmin al panel central: contraseña y, si ya lo configuró, código de doble factor
 * (o un código de recuperación). Sin doble factor configurado, el panel lo pide antes de todo.
 */
class AccesoPanelController extends Controller
{
    private const PENDIENTE = 'panel.verificacion';

    public function formulario(): Response
    {
        return Inertia::render('panel/acceso/Ingresar');
    }

    public function ingresar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $superadmin = Superadmin::query()->where('email', mb_strtolower($datos['email']))->first();

        if ($superadmin === null || ! Hash::check($datos['password'], $superadmin->password)) {
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        if ($superadmin->tieneDosFactores()) {
            $request->session()->put(self::PENDIENTE, ['id' => $superadmin->id, 'recordar' => (bool) ($datos['remember'] ?? false)]);

            return to_route('panel.verificacion');
        }

        $this->iniciarSesion($request, $superadmin, (bool) ($datos['remember'] ?? false));

        return to_route('panel.doble-factor');
    }

    public function verificacion(Request $request): Response|RedirectResponse
    {
        if (! $request->session()->has(self::PENDIENTE)) {
            return to_route('panel.ingresar');
        }

        return Inertia::render('panel/acceso/Verificacion');
    }

    public function verificar(Request $request, TwoFactorAuthenticationProvider $proveedor): RedirectResponse
    {
        /** @var array{id: int, recordar: bool}|null $pendiente */
        $pendiente = $request->session()->get(self::PENDIENTE);
        $superadmin = $pendiente === null ? null : Superadmin::query()->find($pendiente['id']);

        if ($pendiente === null || $superadmin === null || ! $superadmin->tieneDosFactores()) {
            return to_route('panel.ingresar');
        }

        $datos = $request->validate([
            'code' => ['nullable', 'string', 'required_without:recovery_code'],
            'recovery_code' => ['nullable', 'string'],
        ]);

        $codigo = trim((string) ($datos['code'] ?? ''));
        $recuperacion = trim((string) ($datos['recovery_code'] ?? ''));
        $secreto = Fortify::currentEncrypter()->decrypt((string) $superadmin->two_factor_secret);

        if ($codigo !== '' && $proveedor->verify($secreto, $codigo)) {
            // válido
        } elseif ($recuperacion !== '' && in_array($recuperacion, $superadmin->recoveryCodes(), true)) {
            $superadmin->replaceRecoveryCode($recuperacion);
        } else {
            throw ValidationException::withMessages([
                $codigo !== '' ? 'code' : 'recovery_code' => 'El código no es válido.',
            ]);
        }

        $request->session()->forget(self::PENDIENTE);
        $this->iniciarSesion($request, $superadmin, $pendiente['recordar']);

        return redirect()->intended(route('panel.cuentas.index'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::guard('superadmin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('panel.ingresar');
    }

    private function iniciarSesion(Request $request, Superadmin $superadmin, bool $recordar): void
    {
        Auth::guard('superadmin')->login($superadmin, $recordar);
        $request->session()->regenerate();

        AuditoriaCentral::registrar('ingreso', "Ingreso de {$superadmin->email}");
    }
}
