<?php

use App\Http\Controllers\Central\PermisoCertificadoController;
use App\Http\Middleware\AsegurarUsuarioActivo;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\IdentificarEstudio;
use App\Http\Middleware\ModoInstalador;
use App\Http\Middleware\ModoSoloLectura;
use App\Http\Middleware\RequiereEmpresaActiva;
use App\Http\Middleware\SepararPanelYEstudios;
use App\Http\Middleware\VerificarSesionEstudio;
use App\Services\Licencia\LimiteLicenciaExcedido;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        // Sin el grupo «web» (ni sesión ni estudio): la consulta interna de Caddy antes de emitir un certificado.
        then: function (): void {
            Route::get('caddy/permitido', PermisoCertificadoController::class)->middleware('throttle:120,1')->name('caddy.permitido');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->alias([
            'empresa.activa' => RequiereEmpresaActiva::class,
        ]);

        // Modo SaaS: el estudio se identifica en el middleware GLOBAL, antes del enrutamiento. Laravel crea el
        // controlador al reunir los middlewares de la ruta y algunos (login de Fortify) ya abren la sesión:
        // todo debe nacer conectado a la base del estudio.
        $middleware->prepend(IdentificarEstudio::class);

        // Antes que la sesión: sin instalar todavía no hay base de datos. El panel central y los estudios
        // nunca comparten rutas.
        $middleware->web(prepend: [ModoInstalador::class, SepararPanelYEstudios::class]);

        // Invitados y usuarios ya autenticados: cada contexto a su propio inicio.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->routeIs('panel.*') ? route('panel.ingresar') : route('login'));
        $middleware->redirectUsersTo(fn (Request $request) => $request->routeIs('panel.*') ? route('panel.cuentas.index') : route('dashboard'));

        // La sesión de otro estudio se descarta antes de autenticar (modo SaaS).
        $middleware->appendToPriorityList(after: StartSession::class, append: VerificarSesionEstudio::class);

        $middleware->web(append: [
            VerificarSesionEstudio::class,
            AsegurarUsuarioActivo::class,
            ModoSoloLectura::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Límite del plan: aviso y vuelta a la pantalla anterior (no es un error del sistema).
        $exceptions->render(function (LimiteLicenciaExcedido $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return back(303)->withInput();
        });
    })->create();
