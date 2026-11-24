<?php

namespace App\Http\Controllers\Sitio;

use App\Http\Controllers\Controller;
use App\Models\Central\Solicitud;
use App\Models\Central\Superadmin;
use App\Notifications\NuevaSolicitud;
use App\Support\ModoSistema;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

/**
 * Formulario «Pide tu cuenta de prueba» de la web pública (sitio/, servida por Nginx en el dominio raíz).
 * Sin sesión ni CSRF: la web es estática. Lo protegen el límite de envíos de la ruta y el campo trampa «sitio_web».
 * Con JavaScript responde JSON (201 o 422); sin JavaScript redirige a /gracias o de vuelta al formulario.
 */
class SolicitudController extends Controller
{
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        abort_unless(ModoSistema::saas(), 404);

        // Un robot llenó el campo oculto: se le responde como si nada, sin guardar.
        if ($request->filled('sitio_web')) {
            return $this->listo($request);
        }

        $validador = Validator::make($request->all(), [
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', 'in:estudio,empresa'],
            'organizacion' => ['required', 'string', 'max:150'],
            'celular' => ['required', 'string', 'max:20', 'regex:/^[+\d][\d\s-]{5,19}$/'],
            'correo' => ['required', 'email', 'max:150'],
            'empresas' => ['nullable', 'string', 'max:60'],
            'mensaje' => ['nullable', 'string', 'max:1500'],
            'acepta' => ['accepted'],
        ], [
            'nombre.required' => 'Escribe tu nombre.',
            'organizacion.required' => 'Escribe el nombre de tu estudio o empresa.',
            'celular.required' => 'Escribe tu celular.',
            'celular.regex' => 'Revisa el número de celular.',
            'correo.required' => 'Escribe tu correo.',
            'correo.email' => 'Revisa el correo.',
            'acepta.accepted' => 'Necesitamos tu autorización para contactarte.',
        ]);

        if ($validador->fails()) {
            return $request->wantsJson()
                ? response()->json(['errors' => $validador->errors()], 422)
                : redirect('/#prueba');
        }

        $datos = $validador->safe()->except('acepta');
        $solicitud = Solicitud::query()->create([...$datos, 'ip' => $request->ip()]);

        Notification::send(Superadmin::query()->get(), new NuevaSolicitud($solicitud));

        return $this->listo($request);
    }

    private function listo(Request $request): JsonResponse|RedirectResponse
    {
        return $request->wantsJson() ? response()->json(['ok' => true], 201) : redirect('/gracias');
    }
}
