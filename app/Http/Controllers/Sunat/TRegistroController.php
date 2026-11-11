<?php

namespace App\Http\Controllers\Sunat;

use App\Enums\CategoriaOcupacional;
use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use App\Services\Sunat\ExportacionTRegistro;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** T-Registro de la empresa activa: altas y bajas de un rango de fechas, revisión de datos y zip para el PVS. */
class TRegistroController extends Controller
{
    public function __construct(
        private readonly ExportacionTRegistro $exportacion,
        private readonly EmpresaActiva $empresaActiva,
    ) {}

    public function index(Request $request): Response
    {
        $empresa = $this->empresa($request);
        [$desde, $hasta] = $this->rango($request);
        $revision = $this->exportacion->revisar($empresa, $desde, $hasta);

        $fila = fn (bool $baja) => fn (array $r) => $this->fila($r['trabajador'], $r['problemas'], $baja);

        $altas = array_map($fila(false), $revision['altas']);
        $bajas = array_map($fila(true), $revision['bajas']);

        return Inertia::render('tregistro/Index', [
            'empresa' => $empresa->razon_social,
            'ruc' => $empresa->ruc,
            'desde' => $desde->toDateString(),
            'hasta' => $hasta->toDateString(),
            'altas' => $altas,
            'bajas' => $bajas,
            'listo' => ($altas !== [] || $bajas !== []) && collect([...$altas, ...$bajas])->every(fn (array $f) => $f['problemas'] === []),
        ]);
    }

    public function zip(Request $request): BinaryFileResponse|RedirectResponse
    {
        $empresa = $this->empresa($request);
        [$desde, $hasta] = $this->rango($request);

        try {
            $ruta = $this->exportacion->zip($empresa, $desde, $hasta);
        } catch (\DomainException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);

            return to_route('tregistro.index', ['desde' => $desde->toDateString(), 'hasta' => $hasta->toDateString()]);
        }

        return response()->download($ruta, $this->exportacion->nombreZip($empresa, $desde, $hasta), ['Content-Type' => 'application/zip'])
            ->deleteFileAfterSend();
    }

    /** Búsqueda de ocupaciones de la tabla 30 (por código o palabras), filtradas por categoría ocupacional. */
    public function ocupaciones(Request $request): JsonResponse
    {
        $this->empresa($request);
        $categoria = CategoriaOcupacional::tryFrom($request->string('categoria')->toString());

        return response()->json(
            ExportacionTRegistro::buscarOcupaciones($request->string('q')->toString(), $categoria?->columnaOcupacion())
                ->map(fn (object $o) => ['codigo' => $o->codigo, 'nombre' => $o->nombre])->values(),
        );
    }

    /**
     * @param  list<string>  $problemas
     * @return array<string, mixed>
     */
    private function fila(Trabajador $t, array $problemas, bool $baja): array
    {
        return [
            'id' => $t->id,
            'nombre' => $t->nombre_completo,
            'documento' => "{$t->tipo_documento->abreviatura()} {$t->numero_documento}",
            'fecha' => ($baja ? $t->fecha_cese : $t->fecha_ingreso)?->format('d/m/Y'),
            'motivo' => $baja ? $t->motivo_cese?->etiqueta() : null,
            'problemas' => $problemas,
        ];
    }

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable} */
    private function rango(Request $request): array
    {
        $fecha = fn (string $campo) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($campo)) === 1
            ? rescue(fn () => CarbonImmutable::createFromFormat('Y-m-d', (string) $request->query($campo))?->startOfDay(), null, false)
            : null;

        $desde = $fecha('desde') ?? CarbonImmutable::today()->startOfMonth();
        $hasta = $fecha('hasta') ?? $desde->endOfMonth()->startOfDay();

        return $hasta->lt($desde) ? [$hasta, $desde] : [$desde, $hasta];
    }

    private function empresa(Request $request): Empresa
    {
        /** @var User $usuario */
        $usuario = $request->user();

        return $this->empresaActiva->obtener($usuario) ?? abort(409);
    }
}
