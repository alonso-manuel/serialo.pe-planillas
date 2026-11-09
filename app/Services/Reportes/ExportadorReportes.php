<?php

namespace App\Services\Reportes;

use App\Enums\EstadoPeriodo;
use App\Models\Periodo;
use App\Services\Empresas\LogoEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Escribe un reporte (tablas con columnas, filas y totales) en Excel o PDF.
 * Los montos viajan como texto con 2 decimales; solo al escribir el Excel se convierten a número
 * para que el usuario pueda sumar y filtrar (los totales ya vienen calculados sin float).
 */
class ExportadorReportes
{
    /**
     * @param  array{titulo: string, tablas: list<array{titulo: string, columnas: list<array{clave: string, titulo: string, tipo: string}>, filas: list<array<string, string|int|null>>, totales: array<string, string|int|null>|null}>, avisos: list<string>}  $reporte
     * @return string ruta del archivo temporal (borrarlo después de enviarlo)
     */
    public function excel(array $reporte, Periodo $periodo): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'reporte');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $negrita = (new Style)->withFontBold(true);
        $titulo = (new Style)->withFontBold(true)->withFontSize(13);
        $formatoMonto = (new Style)->withFormat('#,##0.00');
        $totalMonto = (new Style)->withFontBold(true)->withFormat('#,##0.00');

        $writer = new Writer;
        $writer->openToFile($ruta);

        foreach ($reporte['tablas'] as $i => $tabla) {
            if ($i > 0) {
                $writer->addNewSheetAndMakeItCurrent();
            }
            $writer->getCurrentSheet()->setName(mb_substr($tabla['titulo'], 0, 31));

            $writer->addRow(new Row([Cell::fromValue($this->encabezado($reporte, $periodo), $titulo)]));
            $writer->addRow(new Row([Cell::fromValue($tabla['titulo'])]));
            foreach ($reporte['avisos'] as $aviso) {
                $writer->addRow(new Row([Cell::fromValue($aviso)]));
            }
            $writer->addRow(new Row([]));
            $writer->addRow(new Row(array_map(fn (array $c) => Cell::fromValue($c['titulo'], $negrita), $tabla['columnas'])));

            foreach ($tabla['filas'] as $fila) {
                $writer->addRow($this->fila($tabla['columnas'], $fila, $formatoMonto, null));
            }
            if ($tabla['totales'] !== null) {
                $writer->addRow($this->fila($tabla['columnas'], $tabla['totales'], $totalMonto, $negrita));
            }
        }

        $writer->close();

        return $ruta;
    }

    /** @param  array{titulo: string, tablas: list<array<string, mixed>>, avisos: list<string>}  $reporte */
    public function pdf(array $reporte, Periodo $periodo): string
    {
        $periodo->loadMissing('empresa');

        return Pdf::loadView('pdf.reporte', [
            'reporte' => $reporte,
            'periodo' => $periodo,
            'empresa' => $periodo->empresa,
            'logo' => app(LogoEmpresa::class)->dataUri($periodo->empresa),
            'borrador' => $periodo->estado !== EstadoPeriodo::Cerrado,
        ])->setPaper('a4', 'landscape')->output();
    }

    /** "Planilla 2026-11 20000000028.xlsx" */
    public function nombre(string $titulo, Periodo $periodo, string $extension): string
    {
        return sprintf('%s %04d-%02d %s.%s', $titulo, $periodo->anio, $periodo->mes, $periodo->empresa->ruc ?? '', $extension);
    }

    /** @param  array{titulo: string}  $reporte */
    private function encabezado(array $reporte, Periodo $periodo): string
    {
        $empresa = $periodo->empresa;
        $borrador = $periodo->estado !== EstadoPeriodo::Cerrado ? ' — BORRADOR (periodo no cerrado)' : '';

        return "{$reporte['titulo']} · {$periodo->etiqueta()} · {$empresa?->razon_social} (RUC {$empresa?->ruc}){$borrador}";
    }

    /**
     * @param  list<array{clave: string, titulo: string, tipo: string}>  $columnas
     * @param  array<string, string|int|null>  $valores
     */
    private function fila(array $columnas, array $valores, Style $monto, ?Style $texto): Row
    {
        return new Row(array_map(function (array $c) use ($valores, $monto, $texto) {
            $valor = $valores[$c['clave']] ?? null;
            if ($valor === null || $valor === '') {
                return Cell::fromValue('', $texto);
            }

            return match ($c['tipo']) {
                // Límite de salida: el Excel guarda números; el monto ya viene redondeado a 2 decimales.
                'monto' => Cell::fromValue((float) $valor, $monto),
                'numero' => Cell::fromValue((int) $valor, $texto),
                default => Cell::fromValue((string) $valor, $texto),
            };
        }, $columnas));
    }
}
