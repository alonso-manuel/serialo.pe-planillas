<?php

namespace App\Domain\Planilla;

/** Retención de quinta categoría de un mes y cómo se llegó a ella. Montos con 2 decimales. */
final readonly class ResultadoQuinta
{
    public function __construct(
        /** Renta bruta anual: percibida + este mes + proyección + gratificaciones por percibir + otros empleadores. */
        public string $rentaProyectada,
        public string $deduccion,
        public string $rentaNeta,
        public string $impuestoAnual,
        /** Retenciones de meses anteriores que se restaron (según el mes). */
        public string $retencionesConsideradas,
        /** 12, 9, 8, 5, 4 o 1 (diciembre y cese). */
        public int $divisor,
        public string $retencionOrdinaria,
        public string $retencionExtraordinaria,
        /** Lo que se descuenta este mes (concepto 0605). */
        public string $retencion,
        /** Diciembre o cese: lo retenido de más en el año (no lo devuelve el empleador en la planilla) ⚠. */
        public string $saldoAFavor,
    ) {}

    /** @return array<string, string|int> para guardar en el detalle de la línea */
    public function detalle(): array
    {
        return [
            'renta_proyectada' => $this->rentaProyectada,
            'deduccion' => $this->deduccion,
            'renta_neta' => $this->rentaNeta,
            'impuesto_anual' => $this->impuestoAnual,
            'retenciones_consideradas' => $this->retencionesConsideradas,
            'divisor' => $this->divisor,
            'retencion_ordinaria' => $this->retencionOrdinaria,
            'retencion_extraordinaria' => $this->retencionExtraordinaria,
            'retencion' => $this->retencion,
            'saldo_a_favor' => $this->saldoAFavor,
        ];
    }
}
