<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarEmpresaActivaRequest;
use App\Services\Empresas\EmpresaActiva;
use Illuminate\Http\RedirectResponse;

class EmpresaActivaController extends Controller
{
    public function update(CambiarEmpresaActivaRequest $request, EmpresaActiva $empresaActiva): RedirectResponse
    {
        $empresa = $request->empresa();
        abort_if($empresa === null, 404);

        $empresaActiva->cambiar($empresa);

        return back();
    }
}
