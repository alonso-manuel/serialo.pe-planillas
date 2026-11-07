<?php

namespace App\Http\Controllers\TasasAfp;

use App\Http\Controllers\Controller;
use App\Http\Requests\TasasAfp\GuardarTasasMesRequest;
use App\Models\TasaAfp;
use App\Services\TasasAfp\ImportadorTasasAfp;
use App\Services\TasasAfp\TasasAfpMensuales;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Grilla mensual de tasas AFP (SBS) y plantilla de importación. */
class TasaAfpController extends Controller
{
    public function __construct(
        private readonly TasasAfpMensuales $tasas,
        private readonly ImportadorTasasAfp $importador,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TasaAfp::class);

        $hoy = CarbonImmutable::today();
        $anio = (int) $request->integer('anio', $hoy->year);
        $mes = (int) $request->integer('mes', $hoy->month);
        $anio = $anio >= 2000 && $anio <= 2100 ? $anio : $hoy->year;
        $mes = $mes >= 1 && $mes <= 12 ? $mes : $hoy->month;
        $anterior = CarbonImmutable::create($anio, $mes, 1)->subMonth();

        return Inertia::render('tasas-afp/Index', [
            'anio' => $anio,
            'mes' => $mes,
            'resumen' => $this->tasas->resumen($anio),
            'grilla' => $this->tasas->grilla($anio, $mes),
            'anterior' => $this->tasas->grilla($anterior->year, $anterior->month),
            'bloqueado' => $this->tasas->mesBloqueado($anio, $mes),
            'afps' => $this->tasas->afps()->map(fn ($a) => ['id' => $a->id, 'codigo' => $a->codigo, 'nombre' => $a->nombre])->values(),
            'importacion' => $this->importador->pendiente(),
        ]);
    }

    public function update(GuardarTasasMesRequest $request, int $anio, int $mes): RedirectResponse
    {
        $this->tasas->guardar($request->filas());

        Inertia::flash('toast', ['type' => 'success', 'message' => sprintf('Tasas AFP de %02d/%d guardadas.', $mes, $anio)]);

        return to_route('tasas-afp.index', ['anio' => $anio, 'mes' => $mes]);
    }

    /**
     * Plantilla .xlsx: encabezado + filas del mes siguiente al último cargado, con
     * esos valores como punto de partida (se corrigen con lo que publique la SBS).
     */
    public function plantilla(): StreamedResponse
    {
        Gate::authorize('create', TasaAfp::class);

        $ultima = TasaAfp::query()->orderByDesc('anio')->orderByDesc('mes')->first();
        $siguiente = $ultima
            ? CarbonImmutable::create($ultima->anio, $ultima->mes, 1)->addMonth()
            : CarbonImmutable::today()->startOfMonth();
        $base = $ultima ? collect($this->tasas->grilla($ultima->anio, $ultima->mes))->keyBy('codigo') : collect();

        return response()->streamDownload(function () use ($siguiente, $base) {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(ImportadorTasasAfp::COLUMNAS));

            foreach ($this->tasas->afps() as $afp) {
                $fila = $base->get($afp->codigo);
                $writer->addRow(Row::fromValues([
                    $siguiente->year,
                    $siguiente->month,
                    $afp->codigo,
                    $fila['aporte_obligatorio'] ?? '',
                    $fila['prima_seguro'] ?? '',
                    $fila['comision_flujo'] ?? '',
                    $fila['comision_mixta'] ?? '',
                    $fila['remuneracion_maxima_asegurable'] ?? '',
                ]));
            }

            $writer->close();
        }, 'plantilla-tasas-afp.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
