<?php

namespace App\Http\Controllers\Instalador;

use App\Domain\Licencia\ClaveLicencia;
use App\Domain\Licencia\ClaveLicenciaInvalida;
use App\Http\Controllers\Controller;
use App\Services\Instalador\ConexionBaseDatos;
use App\Services\Instalador\Instalacion;
use App\Services\Instalador\InstalarSistema;
use App\Services\Instalador\Requisitos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Instalador web en tres pasos: requisitos y código, base de datos, sistema y administrador.
 * Blade simple (sin Inertia): todavía no hay base de datos ni usuarios.
 */
class InstaladorController extends Controller
{
    public function __construct(private readonly Instalacion $instalacion) {}

    public function inicio(Requisitos $requisitos): View
    {
        return view('instalador.requisitos', [
            'paso' => 1,
            'requisitos' => $requisitos->revisar(),
            'cumple' => $requisitos->cumple(),
            'rutaCodigo' => $this->instalacion->rutaCodigoVisible(),
        ]);
    }

    public function codigo(Request $request, Requisitos $requisitos): RedirectResponse
    {
        $request->validate(['codigo' => ['required', 'string', 'max:40']], [], ['codigo' => 'código de instalación']);

        if (! $requisitos->cumple()) {
            return back()->withErrors(['codigo' => 'El servidor aún no cumple todos los requisitos.']);
        }

        if (! $this->instalacion->codigoValido((string) $request->input('codigo'))) {
            return back()->withErrors(['codigo' => 'El código de instalación no es correcto.']);
        }

        $request->session()->put('instalador.autorizado', true);

        return to_route('instalar.base-datos');
    }

    public function baseDatos(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('instalador.autorizado')) {
            return to_route('instalar.inicio');
        }

        return view('instalador.base-datos', ['paso' => 2]);
    }

    public function guardarBaseDatos(Request $request, ConexionBaseDatos $conexion): RedirectResponse
    {
        if (! $request->session()->get('instalador.autorizado')) {
            return to_route('instalar.inicio');
        }

        $datos = $request->validate([
            'host' => ['required', 'string', 'max:255'],
            'puerto' => ['required', 'integer', 'between:1,65535'],
            'nombre' => ['required', 'string', 'max:64'],
            'usuario' => ['required', 'string', 'max:64'],
            'clave' => ['nullable', 'string', 'max:255'],
        ], [], ['host' => 'servidor', 'nombre' => 'nombre de la base de datos', 'clave' => 'contraseña']);

        $bd = [
            'host' => $datos['host'],
            'puerto' => (int) $datos['puerto'],
            'nombre' => $datos['nombre'],
            'usuario' => $datos['usuario'],
            'clave' => $datos['clave'] ?? null,
        ];

        if (($error = $conexion->probar($bd)) !== null) {
            return back()->withInput($request->except('clave'))->withErrors(['conexion' => $error]);
        }

        $request->session()->put('instalador.bd', $bd);

        return to_route('instalar.sistema');
    }

    public function sistema(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('instalador.bd')) {
            return to_route('instalar.inicio');
        }

        return view('instalador.sistema', ['paso' => 3, 'urlSugerida' => $request->root()]);
    }

    public function instalar(Request $request, InstalarSistema $instalador): View|RedirectResponse
    {
        /** @var array{host: string, puerto: int, nombre: string, usuario: string, clave: string|null}|null $bd */
        $bd = $request->session()->get('instalador.bd');

        if ($bd === null) {
            return to_route('instalar.inicio');
        }

        $datos = $request->validate([
            'sistema' => ['required', 'string', 'max:80'],
            'url' => ['required', 'url', 'max:255'],
            'admin_nombre' => ['required', 'string', 'max:255'],
            'admin_correo' => ['required', 'email', 'max:255'],
            'admin_clave' => ['required', 'string', Password::default(), 'confirmed'],
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_puerto' => ['nullable', 'required_with:smtp_host', 'integer', 'between:1,65535'],
            'smtp_usuario' => ['nullable', 'required_with:smtp_host', 'string', 'max:255'],
            'smtp_clave' => ['nullable', 'string', 'max:255'],
            'smtp_cifrado' => ['nullable', 'in:tls,ssl'],
            'correo_remitente' => ['nullable', 'email', 'max:255'],
            'demo' => ['nullable', 'boolean'],
            'licencia' => ['nullable', 'string', 'max:4000'],
        ], [], [
            'sistema' => 'nombre del sistema', 'url' => 'dirección web', 'admin_nombre' => 'nombre',
            'admin_correo' => 'correo', 'admin_clave' => 'contraseña', 'smtp_host' => 'servidor SMTP',
            'smtp_puerto' => 'puerto SMTP', 'smtp_usuario' => 'usuario SMTP', 'correo_remitente' => 'correo remitente',
        ]);

        $licencia = $this->licenciaValida($datos['licencia'] ?? null, $datos['url']);

        try {
            $usuario = $instalador->ejecutar($bd, [
                'nombre' => $datos['sistema'],
                'url' => $datos['url'],
                'smtp_host' => $datos['smtp_host'] ?? null,
                'smtp_puerto' => isset($datos['smtp_puerto']) ? (int) $datos['smtp_puerto'] : null,
                'smtp_usuario' => $datos['smtp_usuario'] ?? null,
                'smtp_clave' => $datos['smtp_clave'] ?? null,
                'smtp_cifrado' => $datos['smtp_cifrado'] ?? null,
                'correo_remitente' => $datos['correo_remitente'] ?? null,
            ], [
                'nombre' => $datos['admin_nombre'],
                'correo' => $datos['admin_correo'],
                'clave' => $datos['admin_clave'],
            ], (bool) ($datos['demo'] ?? false), $licencia);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput($request->except(['admin_clave', 'admin_clave_confirmation', 'smtp_clave']))
                ->withErrors(['instalacion' => 'No se pudo completar la instalación: '.mb_strimwidth($e->getMessage(), 0, 300, '…')]);
        }

        $request->session()->forget('instalador');

        // Se responde aquí mismo: desde ahora /instalar ya no existe.
        return view('instalador.listo', [
            'paso' => 4,
            'correo' => $usuario->email,
            'demo' => (bool) ($datos['demo'] ?? false),
            'cron' => 'php '.base_path('artisan').' schedule:run >> /dev/null 2>&1',
            'login' => rtrim($datos['url'], '/').'/login',
        ]);
    }

    /** La clave es opcional (sin ella empieza la prueba); si se pega, debe ser válida y para este dominio. */
    private function licenciaValida(?string $clave, string $url): ?string
    {
        if ($clave === null || trim($clave) === '') {
            return null;
        }

        try {
            $licencia = ClaveLicencia::leer($clave, (string) config('licencia.clave_publica'));
        } catch (ClaveLicenciaInvalida $e) {
            throw ValidationException::withMessages(['licencia' => $e->getMessage()]);
        }

        $host = (string) parse_url($url, PHP_URL_HOST);
        if (! $licencia->permiteDominio($host)) {
            throw ValidationException::withMessages([
                'licencia' => "La clave es para {$licencia->dominio}, pero la dirección web es {$host}.",
            ]);
        }

        return $clave;
    }
}
