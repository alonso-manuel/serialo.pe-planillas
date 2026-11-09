<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\DiasLaborados;
use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Apertura y mantenimiento de periodos mensuales de una empresa.
 * - El primer periodo puede ser cualquier mes; después solo el mes siguiente al último (PLAME en secuencia).
 * - Al abrir, una línea por trabajador que trabajó en el mes, con días propuestos (mes comercial de 30).
 */
class PeriodosPlanilla
{
    /** @return array{anio: int, mes: int}|null mes siguiente al último periodo (null si no hay ninguno) */
    public function siguiente(Empresa $empresa): ?array
    {
        $ultimo = $this->ultimo($empresa);

        if ($ultimo === null) {
            return null;
        }

        $fecha = CarbonImmutable::create($ultimo->anio, $ultimo->mes, 1)->addMonth();

        return ['anio' => $fecha->year, 'mes' => $fecha->month];
    }

    public function abrir(Empresa $empresa, int $anio, int $mes): Periodo
    {
        if (Periodo::query()->deEmpresa($empresa->id)->where(compact('anio', 'mes'))->exists()) {
            throw ValidationException::withMessages(['mes' => sprintf('El periodo %02d/%d ya está abierto.', $mes, $anio)]);
        }

        $siguiente = $this->siguiente($empresa);
        if ($siguiente !== null && ($siguiente['anio'] !== $anio || $siguiente['mes'] !== $mes)) {
            throw ValidationException::withMessages([
                'mes' => sprintf('Solo puedes abrir %02d/%d, el mes siguiente al último periodo.', $siguiente['mes'], $siguiente['anio']),
            ]);
        }

        return DB::transaction(function () use ($empresa, $anio, $mes) {
            $periodo = $empresa->periodos()->create([
                'anio' => $anio,
                'mes' => $mes,
                'estado' => EstadoPeriodo::Abierto,
            ]);

            foreach ($this->trabajadoresDelMes($empresa, $anio, $mes) as $trabajador) {
                $this->crearLinea($periodo, $trabajador);
            }

            return $periodo;
        });
    }

    /**
     * Agrega a quienes faltan y quita a quienes ya no trabajaron en el mes (si no tienen novedades).
     *
     * @return array{agregados: int, quitados: int, conservados: list<string>}
     */
    public function sincronizar(Periodo $periodo): array
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;
        $corresponden = $this->trabajadoresDelMes($empresa, $periodo->anio, $periodo->mes)->keyBy('id');
        $lineas = $periodo->lineas()->with('trabajador')->withCount('novedades')->get()->keyBy('trabajador_id');

        $agregados = 0;
        $quitados = 0;
        $conservados = [];

        DB::transaction(function () use ($periodo, $corresponden, $lineas, &$agregados, &$quitados, &$conservados) {
            foreach ($corresponden as $id => $trabajador) {
                if (! $lineas->has($id)) {
                    $this->crearLinea($periodo, $trabajador);
                    $agregados++;
                }
            }

            /** @var PlanillaLinea $linea */
            foreach ($lineas as $id => $linea) {
                if ($corresponden->has($id)) {
                    continue;
                }

                if ($linea->getAttribute('novedades_count') > 0) {
                    $conservados[] = $linea->trabajador->nombre_completo;

                    continue;
                }

                $linea->delete();
                $quitados++;
            }
        });

        return compact('agregados', 'quitados', 'conservados');
    }

    /** Solo el último periodo, abierto y sin ningún cálculo. */
    public function eliminar(Periodo $periodo): void
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;

        if (! $this->ultimo($empresa)?->is($periodo)) {
            throw new \DomainException('Solo se puede eliminar el último periodo abierto.');
        }

        if ($periodo->estado !== EstadoPeriodo::Abierto || $periodo->lineas()->whereNotNull('calculado_en')->exists()) {
            throw new \DomainException('Este periodo ya se calculó: no se puede eliminar.');
        }

        DB::transaction(fn () => $periodo->delete());
    }

    /** @return Collection<int, Trabajador> */
    public function trabajadoresDelMes(Empresa $empresa, int $anio, int $mes): Collection
    {
        $primerDia = CarbonImmutable::create($anio, $mes, 1);
        $ultimoDia = $primerDia->endOfMonth();

        return Trabajador::query()
            ->deEmpresa($empresa->id)
            ->whereDate('fecha_ingreso', '<=', $ultimoDia->toDateString())
            ->where(fn (Builder $q) => $q->whereNull('fecha_cese')->orWhereDate('fecha_cese', '>=', $primerDia->toDateString()))
            ->orderBy('apellido_paterno')
            ->get();
    }

    private function crearLinea(Periodo $periodo, Trabajador $trabajador): PlanillaLinea
    {
        $linea = $periodo->lineas()->create([
            'trabajador_id' => $trabajador->id,
            'dias_laborados' => DiasLaborados::proponer(
                $trabajador->fecha_ingreso->toDateTimeImmutable(),
                $trabajador->fecha_cese?->toDateTimeImmutable(),
                $periodo->anio,
                $periodo->mes,
            ),
        ]);
        $linea->setRelation('trabajador', $trabajador);
        app(VacacionesTrabajador::class)->aplicarALinea($linea, $periodo);

        return $linea;
    }

    private function ultimo(Empresa $empresa): ?Periodo
    {
        return Periodo::query()->deEmpresa($empresa->id)->orderByDesc('anio')->orderByDesc('mes')->first();
    }
}
