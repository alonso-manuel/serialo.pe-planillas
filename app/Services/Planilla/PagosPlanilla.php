<?php

namespace App\Services\Planilla;

use App\Models\EntidadFinanciera;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use Brick\Math\BigDecimal;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\CSV\Options;
use OpenSpout\Writer\CSV\Writer as EscritorCsv;
use OpenSpout\Writer\XLSX\Writer as EscritorXlsx;

/**
 * Pagos de la planilla (neto a pagar) para el banco, en formato genérico: cada banco tiene su propio archivo de
 * abono, así que se entrega un Excel/CSV con documento, nombre, banco, cuenta y neto para subirlo con la plantilla
 * del banco. Los trabajadores sin cuenta de sueldo van aparte (pago en efectivo).
 */
class PagosPlanilla
{
    public const ENCABEZADO = ['tipo_documento', 'numero_documento', 'trabajador', 'banco', 'cuenta', 'neto'];

    /**
     * @return array{depositos: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>, efectivo: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>, bancos: array<string, array{cantidad: int, total: string}>}
     */
    public function datos(Periodo $periodo): array
    {
        $lineas = $periodo->lineas()->whereNotNull('calculado_en')->with('trabajador')->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo);
        if ($lineas->isEmpty()) {
            throw new \DomainException('Calcula la planilla antes de generar los pagos.');
        }

        $entidades = EntidadFinanciera::query()->pluck('nombre', 'codigo');
        $depositos = [];
        $efectivo = [];
        $bancos = [];

        foreach ($lineas as $l) {
            $t = $l->trabajador;
            $neto = (string) BigDecimal::of((string) $l->neto_pagar)->toScale(2);
            $cuenta = trim((string) preg_replace('/[\s\-]/', '', (string) $t->cuenta_sueldo));
            $banco = $t->entidad_sueldo === null ? '' : (string) ($entidades[$t->entidad_sueldo] ?? $t->entidad_sueldo);
            $fila = [$t->tipo_documento->abreviatura(), $t->numero_documento, $t->nombre_completo, $banco, $cuenta, $neto];

            if ($cuenta === '') {
                $efectivo[] = $fila;

                continue;
            }

            $depositos[] = $fila;
            $clave = $banco === '' ? 'Banco sin indicar' : $banco;
            $bancos[$clave] ??= ['cantidad' => 0, 'total' => '0.00'];
            $bancos[$clave]['cantidad']++;
            $bancos[$clave]['total'] = (string) BigDecimal::of($bancos[$clave]['total'])->plus($neto)->toScale(2);
        }
        ksort($bancos);

        return ['depositos' => $depositos, 'efectivo' => $efectivo, 'bancos' => $bancos];
    }

    /** Excel con las hojas Depósitos, Efectivo y Resumen. Devuelve la ruta temporal. */
    public function xlsx(Periodo $periodo): string
    {
        $d = $this->datos($periodo);
        $ruta = (string) tempnam(sys_get_temp_dir(), 'pag');
        $w = new EscritorXlsx;
        $w->openToFile($ruta);

        $w->getCurrentSheet()->setName('Depósitos');
        $this->hoja($w, $d['depositos']);
        $w->addNewSheetAndMakeItCurrent()->setName('Efectivo');
        $this->hoja($w, $d['efectivo']);
        $w->addNewSheetAndMakeItCurrent()->setName('Resumen');
        $w->addRow(Row::fromValues(['banco', 'trabajadores', 'total']));
        foreach ($d['bancos'] as $banco => $r) {
            $w->addRow(Row::fromValues([$banco, $r['cantidad'], (float) $r['total']]));
        }
        $w->close();

        return $ruta;
    }

    /** CSV (`;`) solo con los depósitos. Devuelve la ruta temporal. */
    public function csv(Periodo $periodo): string
    {
        $ruta = (string) tempnam(sys_get_temp_dir(), 'pag');
        $w = new EscritorCsv(new Options(FIELD_DELIMITER: ';'));
        $w->openToFile($ruta);
        $w->addRow(Row::fromValues(self::ENCABEZADO));
        foreach ($this->datos($periodo)['depositos'] as $fila) {
            $w->addRow(Row::fromValues($fila));
        }
        $w->close();

        return $ruta;
    }

    /** @param  list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: string}>  $filas */
    private function hoja(EscritorXlsx $w, array $filas): void
    {
        $w->addRow(Row::fromValues(self::ENCABEZADO));
        foreach ($filas as $f) {
            // El neto como número en Excel (se convierte recién al escribir; el cálculo fue sin float).
            $w->addRow(Row::fromValues([$f[0], $f[1], $f[2], $f[3], $f[4], (float) $f[5]]));
        }
    }
}
