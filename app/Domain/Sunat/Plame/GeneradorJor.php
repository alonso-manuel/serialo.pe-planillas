<?php

namespace App\Domain\Sunat\Plame;

/**
 * Archivo .jor del PLAME: jornada laboral por trabajador.
 * Formato: tipo doc | número doc | horas ordinarias | minutos ordinarios | horas sobretiempo | minutos sobretiempo |
 * Ejemplo: 01|12345678|192|0|0|0|
 */
final class GeneradorJor
{
    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, horas_ordinarias: int, minutos_ordinarios: int, horas_extra: int, minutos_extra: int}>  $trabajadores
     */
    public function generar(array $trabajadores): string
    {
        $lineas = array_map(fn (array $t) => LineaPlame::de([
            $t['tipo_documento'],
            $t['numero_documento'],
            $t['horas_ordinarias'],
            $t['minutos_ordinarios'],
            $t['horas_extra'],
            $t['minutos_extra'],
        ]), $trabajadores);

        return LineaPlame::archivo($lineas);
    }
}
