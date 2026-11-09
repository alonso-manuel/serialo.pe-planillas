<?php

namespace App\Domain\Sunat\Plame;

/**
 * Archivo .toc del PLAME (estructura 26): otras condiciones del trabajador.
 * Formato: tipo doc | número doc | Asegura tu Pensión | +Vida | FDSA / Ley 29903 | domiciliado |
 * - Asegura tu Pensión: siempre 0 (SUNAT lo recaudó hasta mayo de 2017).
 * - +Vida: 1 aporta / 0 no aporta (solo puede ser 1 con régimen de salud 00–04 de la tabla 32).
 * - FDSA / Ley 29903: solo para tipo de trabajador 56 (artista) o 98; vacío en los demás.
 * - Domiciliado: 1 domiciliado / 2 no domiciliado.
 * Ejemplo: 01|12345678|0|0||1|
 */
final class GeneradorToc
{
    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, mas_vida?: bool, domiciliado?: bool}>  $trabajadores
     */
    public function generar(array $trabajadores): string
    {
        $lineas = array_map(fn (array $t) => LineaPlame::de([
            $t['tipo_documento'],
            $t['numero_documento'],
            '0',
            ($t['mas_vida'] ?? false) ? '1' : '0',
            '',
            ($t['domiciliado'] ?? true) ? '1' : '2',
        ]), $trabajadores);

        return LineaPlame::archivo($lineas);
    }
}
