<?php

namespace App\Domain\Sunat\Plame;

/**
 * Prestadores de servicios con rentas de cuarta categoría en el PLAME:
 *
 * - `.ps4` — estructura 7: tipo de documento (06 RUC si es domiciliado) | número | apellido paterno |
 *   apellido materno | nombres | domiciliado (1 sí / 2 no) | convenio para evitar la doble tributación (tabla 25) |
 * - `.4ta` — estructura 20 (R.S. 235-2013/SUNAT): tipo y número de documento | tipo de comprobante (tabla 23) |
 *   serie | número | monto total | fecha de emisión | fecha de pago | indicador de retención de cuarta (1/0) |
 *   indicador de aporte pensionario Ley 29903 (3 = no aplica) | importe de ese aporte (vacío si no aplica) |
 *
 * Orden de campos reconstruido del Anexo 3 de estructuras y del anexo de la R.S. 235-2013 ⚠ validar en el PLAME.
 */
final class GeneradorCuarta
{
    /** Ley 29903: sin retención / no aplica (el aporte obligatorio de independientes no está vigente) ⚠. */
    private const SIN_APORTE_PENSIONARIO = '3';

    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, apellido_paterno: string, apellido_materno: string, nombres: string, domiciliado: bool}>  $prestadores
     */
    public function ps4(array $prestadores): string
    {
        return LineaPlame::archivo(array_values(array_map(fn (array $p) => LineaPlame::de([
            $p['tipo_documento'],
            $p['numero_documento'],
            $p['apellido_paterno'],
            $p['apellido_materno'],
            $p['nombres'],
            $p['domiciliado'] ? '1' : '2',
            '0', // tabla 25: ninguno
        ]), $prestadores)));
    }

    /**
     * @param  array<int, array{tipo_documento: string, numero_documento: string, tipo_comprobante: string, serie: string, numero: string, monto: string, fecha_emision: string, fecha_pago: string, retiene: bool}>  $comprobantes
     */
    public function cuarta(array $comprobantes): string
    {
        return LineaPlame::archivo(array_values(array_map(fn (array $c) => LineaPlame::de([
            $c['tipo_documento'],
            $c['numero_documento'],
            $c['tipo_comprobante'],
            $c['serie'],
            $c['numero'],
            $c['monto'],
            $c['fecha_emision'],
            $c['fecha_pago'],
            $c['retiene'] ? '1' : '0',
            self::SIN_APORTE_PENSIONARIO,
            '',
        ]), $comprobantes)));
    }
}
