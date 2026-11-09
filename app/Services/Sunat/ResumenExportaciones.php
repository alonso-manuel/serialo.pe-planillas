<?php

namespace App\Services\Sunat;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Empresas\EmpresaActiva;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/** Datos de las pantallas de archivos oficiales (PLAME, AFPnet): empresas del mes, estado y constancias. */
class ResumenExportaciones
{
    public function __construct(private readonly EmpresaActiva $empresaActiva) {}

    /** @return array{int, int} año y mes pedidos; por defecto el mes anterior (se declara al mes siguiente). */
    public function mes(Request $request): array
    {
        $datos = $request->validate([
            'anio' => ['nullable', 'integer', 'between:2000,2100'],
            'mes' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $anterior = now()->startOfMonth()->subMonth();

        return [(int) ($datos['anio'] ?? $anterior->year), (int) ($datos['mes'] ?? $anterior->month)];
    }

    /**
     * Periodos cerrados y calculados del mes en las empresas que el usuario puede ver.
     *
     * @return Collection<int, Periodo>
     */
    public function periodosListos(User $usuario, int $anio, int $mes): Collection
    {
        return Periodo::query()->with('empresa')
            ->whereIn('empresa_id', $this->empresaActiva->seleccionables($usuario)->pluck('empresas.id'))
            ->where('anio', $anio)->where('mes', $mes)
            ->where('estado', EstadoPeriodo::Cerrado)
            ->whereHas('lineas', fn ($q) => $q->whereNotNull('calculado_en'))
            ->get();
    }

    /** @return array<string, mixed> props de la página */
    public function datos(User $usuario, string $tipo, int $anio, int $mes): array
    {
        $empresas = $this->empresaActiva->seleccionables($usuario)->get();
        $periodos = Periodo::query()->whereIn('empresa_id', $empresas->pluck('id'))
            ->where('anio', $anio)->where('mes', $mes)
            ->withCount(['lineas' => fn ($q) => $q->whereNotNull('calculado_en')])
            ->get()->keyBy('empresa_id');
        $ultimas = Exportacion::query()->with('usuario:id,name')
            ->where('tipo', $tipo)->where('anio', $anio)->where('mes', $mes)
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->orderBy('id')->get()->keyBy('empresa_id');

        return [
            'anio' => $anio,
            'mes' => $mes,
            'etiqueta' => Periodo::MESES[$mes]." {$anio}",
            'empresas' => $empresas->map(function (Empresa $e) use ($periodos, $ultimas) {
                /** @var Periodo|null $p */
                $p = $periodos->get($e->id);
                /** @var Exportacion|null $x */
                $x = $ultimas->get($e->id);

                return [
                    'id' => $e->id,
                    'razon_social' => $e->razon_social,
                    'ruc' => $e->ruc,
                    'periodo' => $p === null ? null : [
                        'id' => $p->id,
                        'estado' => $p->estado->value,
                        'estado_etiqueta' => $p->estado->etiqueta(),
                        'trabajadores' => (int) $p->getAttribute('lineas_count'),
                    ],
                    'listo' => $p !== null && $p->estado === EstadoPeriodo::Cerrado && (int) $p->getAttribute('lineas_count') > 0,
                    'ultima' => $x === null ? null : $this->exportacion($x),
                ];
            })->values()->all(),
            'historial' => Exportacion::query()->with(['usuario:id,name', 'empresa:id,razon_social,ruc'])
                ->where('tipo', $tipo)
                ->whereIn('empresa_id', $empresas->pluck('id'))
                ->latest('id')->limit(20)->get()
                ->map(fn (Exportacion $x) => [
                    ...$this->exportacion($x),
                    'empresa' => $x->empresa?->razon_social,
                    'mes' => sprintf('%02d/%d', $x->mes, $x->anio),
                ])->all(),
        ];
    }

    /** @return array{id: int, fecha: string, usuario: string|null, archivos: list<array{nombre: string, sha256: string, bytes: int}>} */
    private function exportacion(Exportacion $x): array
    {
        return [
            'id' => $x->id,
            'fecha' => $x->created_at->format('d/m/Y H:i'),
            'usuario' => $x->usuario?->name,
            'archivos' => $x->archivos,
        ];
    }
}
