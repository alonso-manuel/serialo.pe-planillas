<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequiereEmpresaActiva;
use App\Http\Requests\CambiarEmpresaActivaRequest;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EmpresaActivaController extends Controller
{
    public function update(CambiarEmpresaActivaRequest $request, EmpresaActiva $empresaActiva, Router $router): RedirectResponse
    {
        $empresa = $request->empresa();
        abort_if($empresa === null, 404);

        /** @var User $usuario */
        $usuario = $request->user();
        $empresaActiva->cambiar($usuario, $empresa);

        return $this->volver($router);
    }

    /**
     * Vuelve a la página anterior, salvo que sea un registro de la empresa anterior (un periodo, un trabajador...):
     * al abrirla, `empresa.activa` reactivaría esa empresa y el cambio no se notaría. En ese caso va al listado
     * del módulo (p. ej. de /periodos/15 a /periodos) o, si no hay, al panel.
     */
    private function volver(Router $router): RedirectResponse
    {
        $anterior = url()->previous();

        try {
            $ruta = $router->getRoutes()->match(Request::create($anterior));
        } catch (HttpException) {
            return redirect()->to($anterior);
        }

        if (! $this->esRegistroDeEmpresa($ruta, $router)) {
            return redirect()->to($anterior);
        }

        $listado = strtok((string) $ruta->getName(), '.').'.index';

        return $router->has($listado) ? to_route($listado) : to_route('dashboard');
    }

    private function esRegistroDeEmpresa(Route $ruta, Router $router): bool
    {
        if ($ruta->parameterNames() === []) {
            return false;
        }

        return in_array(RequiereEmpresaActiva::class, $router->gatherRouteMiddleware($ruta), true);
    }
}
