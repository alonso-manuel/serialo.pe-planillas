<?php

namespace App\Domain\Planilla;

/** Liquidación de beneficios sociales: detalle de cada beneficio y conceptos a pagar (montos en texto, 2 decimales). */
final readonly class ResultadoLiquidacion
{
    /**
     * @param  ResultadoGratificacion|null  $gratificacionPendiente  semestre completo no cobrado en planilla (cese en julio antes del pago)
     * @param  array<string, string>  $conceptos  código PLAME => monto (solo los que corresponden)
     * @param  list<string>  $avisos
     */
    public function __construct(
        public ?ResultadoGratificacion $gratificacionPendiente,
        public ?ResultadoGratificacion $gratificacionTrunca,
        public ?ResultadoCts $cts,
        public string $remuneracionComputableVacaciones,
        public string $promedioVariables,
        public int $vacacionesTruncasMeses,
        public int $vacacionesTruncasDias,
        public string $vacacionesTruncas,
        public int $vacacionesNoGozadasDias,
        public string $vacacionesNoGozadas,
        public int $indemnizacionVacacionalDias,
        public string $indemnizacionVacacional,
        public array $conceptos,
        public string $total,
        public array $avisos = [],
    ) {}
}
