<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraCuarta;
use App\Enums\ClaveParametro;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\PrestadorServicio;
use App\Models\ReciboHonorarios;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Recibos por honorarios de un periodo (mes de pago) con su retención de cuarta categoría.
 * El prestador se crea o actualiza con los datos del recibo. Un periodo cerrado no se modifica (regla 5).
 */
class HonorariosPeriodo
{
    public function __construct(private readonly CalculadoraCuarta $calculadora) {}

    /**
     * @param  array{tipo_documento: string, numero_documento: string, apellido_paterno: string, apellido_materno?: string|null, nombres: string, domiciliado: bool, suspension: bool, tipo_comprobante: string, serie: string, numero: string, fecha_emision: string, fecha_pago: string, monto: string}  $d
     */
    public function registrar(Periodo $periodo, array $d): ReciboHonorarios
    {
        $this->exigirAbierto($periodo);
        $pago = CarbonImmutable::parse($d['fecha_pago']);

        return DB::transaction(function () use ($periodo, $d, $pago) {
            $prestador = PrestadorServicio::query()->firstOrNew([
                'empresa_id' => $periodo->empresa_id,
                'tipo_documento' => $d['tipo_documento'],
                'numero_documento' => $d['numero_documento'],
            ]);
            $prestador->fill([
                'apellido_paterno' => $d['apellido_paterno'],
                'apellido_materno' => $d['apellido_materno'] ?? null,
                'nombres' => $d['nombres'],
                'domiciliado' => $d['domiciliado'],
                // La constancia de suspensión vale por ejercicio: se guarda el año del pago.
                'suspension_anio' => $d['suspension'] ? $pago->year : ($prestador->suspension_anio === $pago->year ? null : $prestador->suspension_anio),
            ])->save();

            $fecha = $pago->toDateString();
            $retencion = $this->calculadora->retencion(
                (string) BigDecimal::of($d['monto'])->toScale(2),
                Parametro::vigente(ClaveParametro::CuartaTasaRetencion, $fecha),
                Parametro::vigente(ClaveParametro::CuartaMontoMinimo, $fecha),
                $prestador->suspendidoEn($pago->year),
                $d['tipo_comprobante'],
            );

            return $periodo->recibosHonorarios()->create([
                'prestador_id' => $prestador->id,
                'tipo_comprobante' => $d['tipo_comprobante'],
                'serie' => mb_strtoupper($d['serie']),
                'numero' => ltrim($d['numero'], '0') ?: '0',
                'fecha_emision' => $d['fecha_emision'],
                'fecha_pago' => $fecha,
                'monto' => $d['monto'],
                'retencion' => $retencion,
            ]);
        });
    }

    public function eliminar(ReciboHonorarios $recibo): void
    {
        $this->exigirAbierto($recibo->periodo);
        $recibo->delete();
    }

    private function exigirAbierto(Periodo $periodo): void
    {
        if ($periodo->estaCerrado()) {
            throw new \DomainException('El periodo está cerrado: reábrelo para modificar los recibos por honorarios.');
        }
    }
}
