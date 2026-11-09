<?php

namespace App\Domain\Sunat\Plame;

/**
 * Archivo .snl del PLAME (estructura 15): días subsidiados y otros no laborados por tipo de suspensión.
 * Formato: tipo doc | número doc | tipo de suspensión (tabla 21) | número de días |
 * Ejemplo: 01|12345678|07|2|
 * Solo se incluyen las suspensiones con días > 0.
 */
final class GeneradorSnl
{
    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, suspensiones: array<string, int>}>  $trabajadores
     *                                                                                                                               suspensiones: código tabla 21 => días
     */
    public function generar(array $trabajadores): string
    {
        $lineas = [];
        foreach ($trabajadores as $t) {
            foreach ($t['suspensiones'] as $tipo => $dias) {
                if ($dias > 0) {
                    $lineas[] = LineaPlame::de([$t['tipo_documento'], $t['numero_documento'], (string) $tipo, $dias]);
                }
            }
        }

        return LineaPlame::archivo($lineas);
    }
}
