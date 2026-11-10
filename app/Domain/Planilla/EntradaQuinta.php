<?php

namespace App\Domain\Planilla;

/**
 * Datos de un trabajador para la retención de quinta categoría de un mes. Montos como string.
 * «Ordinaria» = básico, asignación familiar, remuneración vacacional y gratificaciones;
 * «extraordinaria» = el resto de conceptos afectos del mes (horas extra, bonos, liquidación…) ⚠.
 */
final readonly class EntradaQuinta
{
    /**
     * @param  int  $mes  1 a 12
     * @param  string  $remuneracionOrdinaria  remuneración mensual que se proyecta a los meses que faltan
     * @param  string  $ordinariaDelMes  lo ordinario afecto que se paga este mes (incluye la gratificación de julio/diciembre)
     * @param  string  $extraordinariaDelMes  lo extraordinario afecto que se paga este mes
     * @param  string  $gratificacionesPorPercibir  gratificaciones (con bonificación) de los meses siguientes del año
     * @param  string  $rentaMesesAnteriores  renta afecta pagada por este empleador en los meses anteriores del año
     * @param  array<int, string>  $retencionesPorMes  mes => retención de este empleador en los meses anteriores del año
     * @param  string  $rentaOtrosEmpleadores  renta del año en otros empleadores (certificado de retenciones)
     * @param  string  $retencionOtrosEmpleadores  retenciones del año en otros empleadores (se toman como de meses anteriores) ⚠
     * @param  bool  $cese  último mes del trabajador: no se proyecta y se retiene el saldo del impuesto ⚠
     */
    public function __construct(
        public int $mes,
        public string $remuneracionOrdinaria,
        public string $ordinariaDelMes,
        public string $extraordinariaDelMes,
        public string $gratificacionesPorPercibir,
        public string $rentaMesesAnteriores,
        public array $retencionesPorMes = [],
        public string $rentaOtrosEmpleadores = '0',
        public string $retencionOtrosEmpleadores = '0',
        public bool $cese = false,
    ) {}
}
