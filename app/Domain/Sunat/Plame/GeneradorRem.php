<?php

namespace App\Domain\Sunat\Plame;

/**
 * Archivo .rem del PLAME: ingresos, tributos y descuentos por trabajador.
 * Formato por línea: tipo doc | número doc | código concepto | monto devengado | monto pagado |
 * Ejemplo: 01|12345678|0121|1230.00|1230.00|
 */
final class GeneradorRem
{
    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, conceptos: array<string, string>}>  $trabajadores
     *                                                                                                                               conceptos: código PLAME => monto (devengado = pagado en la versión 1.0)
     */
    public function generar(array $trabajadores): string
    {
        $lineas = [];
        foreach ($trabajadores as $t) {
            foreach ($t['conceptos'] as $codigo => $monto) {
                $lineas[] = LineaPlame::de([$t['tipo_documento'], $t['numero_documento'], $codigo, $monto, $monto]);
            }
        }

        return LineaPlame::archivo($lineas);
    }
}
