<?php

namespace App\Http\Controllers\Reportes;

use App\Enums\EstadoPeriodo;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Reportes\ExportadorReportes;
use App\Services\Reportes\ReportesPlanilla;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/** Reportes de la planilla de la empresa activa en Excel o PDF. */
class ReporteController extends Controller
{
    public function __construct(
        private readonly ReportesPlanilla $reportes,
        private readonly ExportadorReportes $exportador,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        /** @var Empresa $empresa */
        $empresa = $this->empresaActiva->obtener($usuario) ?? abort(409);

        $periodos = Periodo::query()->deEmpresa($empresa->id)
            ->whereHas('lineas', fn ($q) => $q->whereNotNull('calculado_en'))
            ->orderByDesc('anio')->orderByDesc('mes')
            ->get()
            ->map(fn (Periodo $p) => [
                'id' => $p->id,
                'etiqueta' => $p->etiqueta(),
                'estado' => $p->estado->value,
                'estado_etiqueta' => $p->estado->etiqueta(),
                'cerrado' => $p->estado === EstadoPeriodo::Cerrado,
            ])->all();

        return Inertia::render('reportes/Index', [
            'empresa' => $empresa->razon_social,
            'periodos' => $periodos,
            'tipos' => collect(ReportesPlanilla::TIPOS)->map(fn (string $titulo, string $clave) => ['clave' => $clave, 'titulo' => $titulo])->values()->all(),
        ]);
    }

    public function descargar(Periodo $periodo, string $tipo, string $formato): HttpResponse|RedirectResponse
    {
        Gate::authorize('view', $periodo);

        try {
            $reporte = $this->reportes->generar($periodo, $tipo);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('reportes.index');
        }

        $titulo = ReportesPlanilla::TIPOS[$tipo];

        if ($formato === 'pdf') {
            return response($this->exportador->pdf($reporte, $periodo), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$this->exportador->nombre($titulo, $periodo, 'pdf').'"',
            ]);
        }

        return response()->download($this->exportador->excel($reporte, $periodo), $this->exportador->nombre($titulo, $periodo, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }
}
