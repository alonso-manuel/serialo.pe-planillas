<?php

namespace App\Services\Planilla;

use App\Enums\EstadoPeriodo;
use App\Jobs\CalcularPlanillaEmpresaJob;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * Cálculo del mismo mes en todas las empresas visibles del usuario, en cola (un trabajo por empresa).
 * El avance se guarda en caché por lote y por empresa: la pantalla lo consulta cada pocos segundos.
 * - Empresas sin el mes abierto: «sin_periodo» (no se abren solas).
 * - Periodo cerrado: «omitido».
 * - Problemas de datos en una empresa no detienen a las demás.
 */
class CalculoMasivo
{
    private const DURACION = 86400; // el resultado se puede consultar durante un día

    public function __construct(private readonly Repository $cache) {}

    public function iniciar(User $usuario, int $anio, int $mes): string
    {
        $id = (string) Str::uuid();
        $empresas = $usuario->empresasVisibles()->where('activo', true)->orderBy('razon_social')->get();
        $periodos = Periodo::query()
            ->whereIn('empresa_id', $empresas->pluck('id'))
            ->where(['anio' => $anio, 'mes' => $mes])
            ->get()
            ->keyBy('empresa_id');

        $trabajos = [];
        $orden = [];

        foreach ($empresas as $empresa) {
            $orden[] = $empresa->id;
            /** @var Periodo|null $periodo */
            $periodo = $periodos->get($empresa->id);

            $resultado = match (true) {
                $periodo === null => ['estado' => 'sin_periodo', 'detalle' => 'El mes no está abierto en esta empresa.'],
                $periodo->estado === EstadoPeriodo::Cerrado => ['estado' => 'omitido', 'detalle' => 'Periodo cerrado.'],
                default => ['estado' => 'pendiente'],
            };

            $this->guardarResultado($id, $empresa, $periodo, $resultado);

            if ($resultado['estado'] === 'pendiente' && $periodo !== null) {
                $trabajos[] = new CalcularPlanillaEmpresaJob($id, $periodo->id);
            }
        }

        $this->cache->put($this->clave($id), [
            'usuario_id' => $usuario->id,
            'anio' => $anio,
            'mes' => $mes,
            'empresas' => $orden,
        ], self::DURACION);

        if ($trabajos !== []) {
            Bus::batch($trabajos)
                ->name(sprintf('Cálculo de planillas %02d/%d', $mes, $anio))
                ->allowFailures()
                ->dispatch();
        }

        return $id;
    }

    /** Lo ejecuta cada trabajo de la cola. */
    public function calcularEmpresa(string $id, Periodo $periodo, CalcularPlanillaPeriodo $calculo): void
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;

        try {
            $resumen = $calculo->ejecutar($periodo);
            $resultado = ['estado' => 'calculado', ...$resumen];
        } catch (ProblemasDeCalculo $e) {
            $resultado = ['estado' => 'con_problemas', 'problemas' => $e->problemas];
        } catch (\Throwable $e) {
            report($e);
            $resultado = ['estado' => 'con_problemas', 'problemas' => ['Error inesperado al calcular: '.$e->getMessage()]];
        }

        $this->guardarResultado($id, $empresa, $periodo, $resultado);
    }

    /**
     * Estado del lote para la pantalla; null si no existe o es de otro usuario.
     *
     * @return array<string, mixed>|null
     */
    public function estado(string $id, User $usuario): ?array
    {
        /** @var array{usuario_id: int, anio: int, mes: int, empresas: list<int>}|null $lote */
        $lote = $this->cache->get($this->clave($id));

        if ($lote === null || $lote['usuario_id'] !== $usuario->id) {
            return null;
        }

        $empresas = array_map(fn (int $empresaId) => $this->cache->get($this->clave($id, $empresaId)), $lote['empresas']);
        $pendientes = count(array_filter($empresas, fn ($e) => ($e['estado'] ?? 'pendiente') === 'pendiente'));

        return [
            'id' => $id,
            'mes' => sprintf('%02d/%d', $lote['mes'], $lote['anio']),
            'total' => count($empresas),
            'pendientes' => $pendientes,
            'terminado' => $pendientes === 0,
            'empresas' => array_values(array_filter($empresas)),
        ];
    }

    /** @param array<string, mixed> $resultado */
    private function guardarResultado(string $id, Empresa $empresa, ?Periodo $periodo, array $resultado): void
    {
        $this->cache->put($this->clave($id, $empresa->id), [
            'empresa' => $empresa->razon_social,
            'periodo_id' => $periodo?->id,
            ...$resultado,
        ], self::DURACION);
    }

    private function clave(string $id, ?int $empresaId = null): string
    {
        return "calculo-masivo:{$id}".($empresaId === null ? '' : ":{$empresaId}");
    }
}
