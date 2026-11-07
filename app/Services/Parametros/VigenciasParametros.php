<?php

namespace App\Services\Parametros;

use App\Enums\ClaveParametro;
use App\Enums\EstadoPeriodo;
use App\Models\Parametro;
use App\Models\Periodo;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Vigencias de los parámetros legales (regla 3: un valor ya usado nunca cambia).
 *
 * Una vigencia rige desde su `vigente_desde` hasta el mes anterior a la siguiente vigencia
 * de la misma clave. Solo se puede anular si ningún periodo CERRADO cae en ese rango:
 * los periodos abiertos o calculados se recalculan con el valor corregido.
 */
class VigenciasParametros
{
    /**
     * Resumen por clave para la pantalla de parámetros, en el orden del enum.
     *
     * @return list<array<string, mixed>>
     */
    public function resumen(CarbonImmutable $hoy): array
    {
        /** @var Collection<string, Collection<int, Parametro>> $porClave */
        $porClave = Parametro::query()
            ->orderByDesc('vigente_desde')
            ->get()
            ->groupBy(fn (Parametro $p) => $p->clave->value);

        return array_map(function (ClaveParametro $clave) use ($porClave, $hoy) {
            /** @var Collection<int, Parametro> $vigencias */
            $vigencias = $porClave->get($clave->value, collect());

            $vigente = $vigencias->first(fn (Parametro $p) => $p->vigente_desde->lte($hoy));
            $proxima = $vigencias->filter(fn (Parametro $p) => $p->vigente_desde->gt($hoy))->last();

            return [
                'clave' => $clave->value,
                'etiqueta' => $clave->etiqueta(),
                'descripcion' => $clave->descripcion(),
                'tipo' => $clave->tipo(),
                'vigente' => $vigente ? $this->fila($vigente) : null,
                'proxima' => $proxima ? $this->fila($proxima) : null,
                'historial' => $vigencias->map(fn (Parametro $p) => [
                    ...$this->fila($p),
                    'anulable' => $this->esAnulable($p),
                ])->values()->all(),
            ];
        }, ClaveParametro::cases());
    }

    /**
     * @param  array{clave: ClaveParametro, valor: string, vigente_desde: string, base_legal: string}  $datos
     */
    public function registrar(array $datos): Parametro
    {
        return Parametro::create($datos);
    }

    public function esAnulable(Parametro $parametro): bool
    {
        $siguiente = Parametro::query()
            ->where('clave', $parametro->clave)
            ->where('vigente_desde', '>', $parametro->vigente_desde)
            ->orderBy('vigente_desde')
            ->first();

        $desde = $this->anioMes($parametro->vigente_desde);
        $hasta = $siguiente ? $this->anioMes($siguiente->vigente_desde) : null;

        return ! Periodo::query()
            ->where('estado', EstadoPeriodo::Cerrado)
            ->whereRaw('anio * 100 + mes >= ?', [$desde])
            ->when($hasta !== null, fn (Builder $q) => $q->whereRaw('anio * 100 + mes < ?', [$hasta]))
            ->exists();
    }

    public function anular(Parametro $parametro): void
    {
        if (! $this->esAnulable($parametro)) {
            throw new \DomainException(
                'Esta vigencia ya se usó en un periodo cerrado y no se puede anular. Registra una vigencia nueva con el valor correcto.'
            );
        }

        $parametro->delete();
    }

    /**
     * @return array{id: int, valor: string, valor_mostrado: string, vigente_desde: string, base_legal: string|null, verificar: bool}
     */
    private function fila(Parametro $p): array
    {
        return [
            'id' => $p->id,
            'valor' => $p->valor,
            'valor_mostrado' => $p->clave->formatear($p->valor),
            'vigente_desde' => $p->vigente_desde->toDateString(),
            'base_legal' => $p->base_legal,
            'verificar' => str_contains(mb_strtoupper((string) $p->base_legal), 'VERIFICAR'),
        ];
    }

    private function anioMes(CarbonImmutable $fecha): int
    {
        return $fecha->year * 100 + $fecha->month;
    }
}
