<?php

namespace App\Services\Planilla;

use App\Enums\TipoConcepto;
use App\Models\Empresa;
use App\Models\EntidadFinanciera;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\Trabajador;
use App\Services\Empresas\LogoEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;

/**
 * Documentos del cese: hoja de liquidación de beneficios sociales, certificado de trabajo y carta al banco
 * para liberar la CTS (D.S. 001-97-TR, art. 45). Llevan «BORRADOR» mientras la liquidación no está cerrada.
 */
class DocumentosLiquidacion
{
    public function __construct(private readonly LogoEmpresa $logos) {}

    public function hoja(Liquidacion $liquidacion): string
    {
        $liquidacion->loadMissing(['trabajador', 'empresa']);
        /** @var Trabajador $t */
        $t = $liquidacion->trabajador;
        /** @var Empresa $empresa */
        $empresa = $liquidacion->empresa;

        return Pdf::loadView('pdf.liquidacion', [
            'liquidacion' => $liquidacion,
            'trabajador' => $t,
            'empresa' => $empresa,
            'logo' => $this->logos->dataUri($empresa),
            'tiempo' => $this->tiempoServicios($t, $liquidacion),
            'planilla' => $this->planillaDelCese($liquidacion),
            'mesCese' => Periodo::MESES[$liquidacion->fecha_cese->month].' '.$liquidacion->fecha_cese->year,
            'borrador' => ! $liquidacion->estaCerrada(),
        ])->setPaper('a4')->output();
    }

    public function certificado(Trabajador $trabajador): string
    {
        $trabajador->loadMissing('empresa');
        /** @var Empresa $empresa */
        $empresa = $trabajador->empresa;

        return Pdf::loadView('pdf.certificado-trabajo', [
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'logo' => $this->logos->dataUri($empresa),
        ])->setPaper('a4')->output();
    }

    public function cartaCts(Liquidacion $liquidacion): string
    {
        $liquidacion->loadMissing(['trabajador', 'empresa']);
        /** @var Trabajador $t */
        $t = $liquidacion->trabajador;
        /** @var Empresa $empresa */
        $empresa = $liquidacion->empresa;

        return Pdf::loadView('pdf.carta-cts', [
            'liquidacion' => $liquidacion,
            'trabajador' => $t,
            'empresa' => $empresa,
            'logo' => $this->logos->dataUri($empresa),
            'entidad' => $t->entidad_cts === null ? null : (EntidadFinanciera::query()->where('codigo', $t->entidad_cts)->value('nombre') ?? $t->entidad_cts),
            'borrador' => ! $liquidacion->estaCerrada(),
        ])->setPaper('a4')->output();
    }

    public function nombre(string $documento, Trabajador $trabajador): string
    {
        return Str::slug("{$documento} {$trabajador->numero_documento} {$trabajador->apellido_paterno}").'.pdf';
    }

    /** @return array{anios: int, meses: int, dias: int} */
    private function tiempoServicios(Trabajador $t, Liquidacion $l): array
    {
        $diff = $t->fecha_ingreso->diff($l->fecha_cese->addDay());

        return ['anios' => $diff->y, 'meses' => $diff->m, 'dias' => $diff->d];
    }

    /**
     * Línea calculada de la planilla del mes del cese, separada en remuneraciones, beneficios y descuentos.
     *
     * @return array{remuneraciones: list<array{codigo: string, nombre: string, monto: string}>, beneficios: list<array{codigo: string, nombre: string, monto: string}>, descuentos: list<array{codigo: string, nombre: string, monto: string}>, neto: string}|null
     */
    private function planillaDelCese(Liquidacion $l): ?array
    {
        $linea = PlanillaLinea::query()->where('trabajador_id', $l->trabajador_id)->whereNotNull('calculado_en')
            ->whereHas('periodo', fn ($q) => $q->where('empresa_id', $l->empresa_id)
                ->where('anio', $l->fecha_cese->year)->where('mes', $l->fecha_cese->month))
            ->with('conceptos.concepto')->first();

        // Solo si esa planilla ya se recalculó con esta liquidación (si no, mostraría el mes sin los beneficios).
        if ($linea === null || $linea->calculado_en === null || $l->calculado_en === null || $linea->calculado_en->lt($l->calculado_en)) {
            return null;
        }

        $beneficios = array_values(Liquidacion::CONCEPTOS);
        $grupos = ['remuneraciones' => [], 'beneficios' => [], 'descuentos' => []];
        foreach ($linea->conceptos->sortBy(fn (PlanillaConcepto $c) => $c->concepto->codigo_plame) as $c) {
            $fila = ['codigo' => $c->concepto->codigo_plame, 'nombre' => $c->concepto->nombre, 'monto' => (string) $c->monto_pagado];
            match (true) {
                $c->concepto->tipo === TipoConcepto::AporteEmpleador => null,
                $c->concepto->tipo !== TipoConcepto::Ingreso => $grupos['descuentos'][] = $fila,
                in_array($fila['codigo'], $beneficios, true) => $grupos['beneficios'][] = $fila,
                default => $grupos['remuneraciones'][] = $fila,
            };
        }

        return [...$grupos, 'neto' => (string) $linea->neto_pagar];
    }
}
