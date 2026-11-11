<?php

namespace App\Domain\Sunat\TRegistro;

use App\Domain\Sunat\Plame\LineaPlame;

/**
 * Archivos de importación del T-Registro (Anexo 3 de la R.M. 121-2011-TR y modificatorias; manual del
 * PVS T-Registro, actualizado al 18/09/2023). Campos separados por «|» y un «|» al final de cada línea;
 * un campo que no aplica va vacío («||»).
 *
 * - RP_<RUC>.ide — estructura 04, datos personales (41 campos; la dirección se deja vacía: es opcional).
 * - RP_<RUC>.tra — estructura 05, datos del trabajador (23 campos).
 * - RP_<RUC>.per — estructura 11, periodos: en el alta, el periodo laboral, el tipo de trabajador,
 *   el régimen de salud y el pensionario; en la baja, el periodo laboral con su fecha de fin y motivo.
 * - RP_<RUC>.est — estructura 17, establecimiento donde labora.
 */
final class GeneradorTRegistro
{
    /** Categoría «trabajador» de la estructura 11. */
    private const CATEGORIA_TRABAJADOR = '1';

    /** @param  list<PrestadorTRegistro>  $altas */
    public function ide(array $altas): string
    {
        return LineaPlame::archivo(array_map(fn (PrestadorTRegistro $p) => LineaPlame::de([
            ...$this->identificacion($p),
            $p->fechaNacimiento,
            $p->apellidoPaterno,
            $p->apellidoMaterno,
            $p->nombres,
            $p->sexo,
            $p->nacionalidad,
            '',                 // 10 código de larga distancia (no se usa con celular)
            $p->telefono,
            $p->correo,
            ...array_fill(0, 29, ''), // 13–41 dirección 1, dirección 2 e indicador de centro asistencial
        ]), $altas));
    }

    /** @param  list<PrestadorTRegistro>  $altas */
    public function tra(array $altas): string
    {
        return LineaPlame::archivo(array_map(fn (PrestadorTRegistro $p) => LineaPlame::de([
            ...$this->identificacion($p),
            $p->regimenLaboral,
            $p->situacionEducativa,
            $p->ocupacion,
            $p->discapacidad ? '1' : '0',
            $p->cuspp,
            '',                 // 9 SCTR pensión (opcional)
            $p->tipoContrato,
            '', '', '',         // 11–13 jornada atípica, máxima y nocturna (opcionales)
            $p->sindicalizado ? '1' : '0',
            $p->periodicidad,
            $p->remuneracionBasica,
            '1',                // 17 situación: activo
            '0',                // 18 rentas de 5ta exoneradas
            $p->situacionEspecial,
            $p->tipoPago,
            $p->categoriaOcupacional,
            '0',                // 22 convenio para evitar la doble tributación: ninguno
            '',                 // 23 RUC (solo CAS)
        ]), $altas));
    }

    /**
     * @param  list<PrestadorTRegistro>  $altas
     * @param  list<PrestadorTRegistro>  $bajas
     */
    public function per(array $altas, array $bajas): string
    {
        $lineas = [];

        foreach ($altas as $p) {
            $lineas[] = $this->periodo($p, '1', $p->fechaIngreso, '', '');  // alta del periodo laboral: indicador vacío
            $lineas[] = $this->periodo($p, '2', $p->fechaIngreso, '', $p->tipoTrabajador);
            $lineas[] = $this->periodo($p, '3', $p->fechaIngreso, '', $p->regimenSalud);
            $lineas[] = $this->periodo($p, '4', $p->fechaIngreso, '', $p->regimenPensionario);
        }

        foreach ($bajas as $p) {
            $lineas[] = $this->periodo($p, '1', $p->fechaIngreso, $p->fechaCese, $p->motivoCese);
        }

        return LineaPlame::archivo($lineas);
    }

    /** @param  list<PrestadorTRegistro>  $altas */
    public function est(array $altas): string
    {
        return LineaPlame::archivo(array_map(fn (PrestadorTRegistro $p) => LineaPlame::de([
            ...$this->identificacion($p),
            $p->ruc,
            $p->establecimiento,
        ]), $altas));
    }

    private function periodo(PrestadorTRegistro $p, string $tipoRegistro, string $inicio, string $fin, string $indicador): string
    {
        return LineaPlame::de([
            ...$this->identificacion($p),
            self::CATEGORIA_TRABAJADOR,
            $tipoRegistro,
            $inicio,
            $fin,
            $indicador,
            '', // 9 EPS / servicios propios (solo régimen de salud 01 o 03)
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function identificacion(PrestadorTRegistro $p): array
    {
        return [$p->tipoDocumento, $p->numeroDocumento, $p->paisEmisor];
    }
}
