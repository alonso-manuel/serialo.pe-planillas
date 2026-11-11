<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraPrestamo;
use App\Enums\EstadoPeriodo;
use App\Models\Periodo;
use App\Models\Prestamo;
use App\Models\PrestamoCuota;
use App\Models\Trabajador;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Préstamos al trabajador: alta con su cronograma, cuotas del mes para la planilla (concepto 0706) y estado de cuenta.
 * Una cuota cuenta como descontada cuando la planilla de su mes está cerrada.
 */
class PrestamosTrabajador
{
    /** Tabla 22: otros descuentos no deducibles de la base imponible. */
    public const CODIGO = '0706';

    public function __construct(private readonly CalculadoraPrestamo $calculadora) {}

    public function registrar(Trabajador $trabajador, string $fecha, string $monto, int $cuotas, string $mesInicio, ?string $glosa): Prestamo
    {
        [$anio, $mes] = array_map('intval', explode('-', $mesInicio));

        $periodoCerrado = Periodo::query()->where('empresa_id', $trabajador->empresa_id)
            ->where('estado', EstadoPeriodo::Cerrado)
            ->where(fn ($q) => $q->where('anio', '>', $anio)->orWhere(fn ($q) => $q->where('anio', $anio)->where('mes', '>=', $mes)))
            ->orderBy('anio')->orderBy('mes')->first();
        if ($periodoCerrado !== null) {
            throw new \DomainException("La planilla de {$periodoCerrado->etiqueta()} ya está cerrada: la primera cuota debe ser en un mes posterior.");
        }

        return DB::transaction(function () use ($trabajador, $fecha, $monto, $cuotas, $anio, $mes, $glosa) {
            $prestamo = $trabajador->prestamos()->create(['fecha' => $fecha, 'monto' => $monto, 'numero_cuotas' => $cuotas, 'glosa' => $glosa]);
            foreach ($this->calculadora->cronograma($monto, $cuotas, $anio, $mes) as $c) {
                $prestamo->cuotas()->create($c);
            }

            return $prestamo;
        });
    }

    /** Solo si ninguna cuota cayó en una planilla cerrada. */
    public function eliminar(Prestamo $prestamo): void
    {
        foreach ($prestamo->cuotas as $c) {
            if ($this->periodoCerrado($prestamo->trabajador->empresa_id, $c->anio, $c->mes)) {
                throw new \DomainException('Ya se descontaron cuotas en planillas cerradas: no se puede eliminar el préstamo.');
            }
        }

        $prestamo->delete();
    }

    /**
     * Cuotas a descontar en el periodo, por trabajador.
     *
     * @return array<int, string> trabajador_id => monto
     */
    public function cuotasDelPeriodo(Periodo $periodo): array
    {
        $filas = PrestamoCuota::query()
            ->join('prestamos', 'prestamos.id', '=', 'prestamo_cuotas.prestamo_id')
            ->join('trabajadores', 'trabajadores.id', '=', 'prestamos.trabajador_id')
            ->where('trabajadores.empresa_id', $periodo->empresa_id)
            ->where('prestamo_cuotas.anio', $periodo->anio)
            ->where('prestamo_cuotas.mes', $periodo->mes)
            ->groupBy('prestamos.trabajador_id')
            ->selectRaw('prestamos.trabajador_id as trabajador, SUM(prestamo_cuotas.monto) as total')
            ->get();

        $cuotas = [];
        foreach ($filas as $f) {
            $cuotas[(int) $f->getAttribute('trabajador')] = (string) BigDecimal::of((string) $f->getAttribute('total'))->toScale(2);
        }

        return $cuotas;
    }

    /**
     * Estado de cuenta de los préstamos del trabajador.
     *
     * @return list<array{id: int, fecha: string, monto: string, glosa: string|null, saldo: string, eliminable: bool, cuotas: list<array{numero: int, mes: string, monto: string, descontada: bool}>}>
     */
    public function estadoDeCuenta(Trabajador $trabajador): array
    {
        $cerrados = Periodo::query()->where('empresa_id', $trabajador->empresa_id)->where('estado', EstadoPeriodo::Cerrado)
            ->get(['anio', 'mes'])->map(fn (Periodo $p) => "{$p->anio}-{$p->mes}")->flip();

        $lista = [];
        foreach ($trabajador->prestamos()->with('cuotas')->orderByDesc('fecha')->get() as $p) {
            $saldo = BigDecimal::of((string) $p->monto);
            $cuotas = [];
            foreach ($p->cuotas as $c) {
                $descontada = $cerrados->has("{$c->anio}-{$c->mes}");
                if ($descontada) {
                    $saldo = $saldo->minus((string) $c->monto);
                }
                $cuotas[] = ['numero' => $c->numero, 'mes' => sprintf('%02d/%d', $c->mes, $c->anio), 'monto' => (string) $c->monto, 'descontada' => $descontada];
            }
            $lista[] = [
                'id' => $p->id,
                'fecha' => $p->fecha->format('d/m/Y'),
                'monto' => (string) $p->monto,
                'glosa' => $p->glosa,
                'saldo' => (string) $saldo->toScale(2),
                'eliminable' => ! collect($cuotas)->contains('descontada', true),
                'cuotas' => $cuotas,
            ];
        }

        return $lista;
    }

    /** Saldo pendiente total del trabajador (cuotas aún no descontadas en planillas cerradas). */
    public function saldo(Trabajador $trabajador): string
    {
        return (string) array_reduce($this->estadoDeCuenta($trabajador), fn (BigDecimal $s, array $p) => $s->plus($p['saldo']), BigDecimal::zero())->toScale(2);
    }

    private function periodoCerrado(int $empresaId, int $anio, int $mes): bool
    {
        return Periodo::query()->where('empresa_id', $empresaId)->where('anio', $anio)->where('mes', $mes)->where('estado', EstadoPeriodo::Cerrado)->exists();
    }
}
