<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraLiquidacion;
use App\Domain\Planilla\CalculadoraQuinta;
use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Services\Empresas\FirmaDigitalPdf;
use App\Services\Empresas\LogoEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Brick\Math\BigDecimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Certificado de rentas y retenciones de quinta categoría del ejercicio (art. 45 del Reglamento de la LIR):
 * rentas afectas pagadas por la empresa en sus planillas **cerradas** del año, deducción, renta neta,
 * impuesto anual y retenciones efectuadas. Se entrega por el ejercicio o al cese. Con firma digital si la hay.
 */
class CertificadoQuinta
{
    /** Agrupación de conceptos afectos para el certificado. */
    private const GRATIFICACIONES = [
        CalculadoraGratificacion::GRATIFICACION, CalculadoraGratificacion::BONIFICACION,
        CalculadoraLiquidacion::GRATIFICACION_TRUNCA, CalculadoraLiquidacion::BONIFICACION_TRUNCA,
    ];

    public function __construct(
        private readonly CalculadoraQuinta $calculadora,
        private readonly ParametrosRepositorio $parametros,
        private readonly FirmaDigitalPdf $firma,
    ) {}

    /**
     * @return array{anio: int, desde: string, hasta: string, remuneraciones: string, gratificaciones: string, otros: string, renta_bruta: string, deduccion: string, renta_neta: string, impuesto: string, retenido: string, diferencia: string, pendientes: int}
     */
    public function datos(Trabajador $trabajador, int $anio): array
    {
        $filas = DB::table('planilla_conceptos as pc')
            ->join('planilla_lineas as pl', 'pl.id', '=', 'pc.planilla_linea_id')
            ->join('periodos as p', 'p.id', '=', 'pl.periodo_id')
            ->join('conceptos as c', 'c.id', '=', 'pc.concepto_id')
            ->where('pl.trabajador_id', $trabajador->id)
            ->where('p.empresa_id', $trabajador->empresa_id)
            ->where('p.anio', $anio)
            ->where('p.estado', EstadoPeriodo::Cerrado->value)
            ->where(fn ($q) => $q->where('c.afecto_quinta', true)->orWhere('c.codigo_plame', RetencionQuinta::CODIGO))
            ->groupBy('c.codigo_plame')
            ->selectRaw('c.codigo_plame as codigo, SUM(pc.monto_pagado) as monto')
            ->get();

        $grupos = ['remuneraciones' => BigDecimal::zero(), 'gratificaciones' => BigDecimal::zero(), 'otros' => BigDecimal::zero()];
        $retenido = BigDecimal::zero();
        foreach ($filas as $f) {
            $codigo = (string) $f->codigo;
            $monto = (string) $f->monto;
            if ($codigo === RetencionQuinta::CODIGO) {
                $retenido = $retenido->plus($monto);
            } elseif (in_array($codigo, self::GRATIFICACIONES, true)) {
                $grupos['gratificaciones'] = $grupos['gratificaciones']->plus($monto);
            } elseif (in_array($codigo, RetencionQuinta::ORDINARIOS, true)) {
                $grupos['remuneraciones'] = $grupos['remuneraciones']->plus($monto);
            } else {
                $grupos['otros'] = $grupos['otros']->plus($monto);
            }
        }

        $bruta = $grupos['remuneraciones']->plus($grupos['gratificaciones'])->plus($grupos['otros']);
        $parametros = $this->parametros->quintaAl("{$anio}-12-31");
        [$neta, $impuesto] = $this->calculadora->impuestoDelEjercicio((string) $bruta, $parametros);

        $inicio = CarbonImmutable::create($anio, 1, 1);
        $fin = CarbonImmutable::create($anio, 12, 31);
        $desde = $trabajador->fecha_ingreso->greaterThan($inicio) ? CarbonImmutable::parse($trabajador->fecha_ingreso) : $inicio;
        $hasta = $trabajador->fecha_cese !== null && $trabajador->fecha_cese->lessThan($fin) ? CarbonImmutable::parse($trabajador->fecha_cese) : $fin;

        // Planillas del año con este trabajador que aún no están cerradas (no entran al certificado).
        $pendientes = Periodo::query()->where('empresa_id', $trabajador->empresa_id)->where('anio', $anio)
            ->where('estado', '!=', EstadoPeriodo::Cerrado)
            ->whereHas('lineas', fn ($q) => $q->where('trabajador_id', $trabajador->id))->count();

        $texto = fn (BigDecimal $v) => (string) $v->toScale(2);

        return [
            'anio' => $anio,
            'desde' => $desde->format('d/m/Y'),
            'hasta' => $hasta->format('d/m/Y'),
            'remuneraciones' => $texto($grupos['remuneraciones']),
            'gratificaciones' => $texto($grupos['gratificaciones']),
            'otros' => $texto($grupos['otros']),
            'renta_bruta' => $texto($bruta),
            'deduccion' => $texto(BigDecimal::of($parametros->uit)->multipliedBy($parametros->deduccionUit)),
            'renta_neta' => $neta,
            'impuesto' => $impuesto,
            'retenido' => $texto($retenido),
            'diferencia' => $texto(BigDecimal::of($impuesto)->minus($retenido)),
            'pendientes' => $pendientes,
        ];
    }

    public function pdf(Trabajador $trabajador, int $anio): string
    {
        $trabajador->loadMissing('empresa');
        /** @var Empresa $empresa */
        $empresa = $trabajador->empresa;

        $pdf = Pdf::loadView('pdf.certificado-quinta', [
            'trabajador' => $trabajador,
            'empresa' => $empresa,
            'logo' => app(LogoEmpresa::class)->dataUri($empresa),
            'c' => $this->datos($trabajador, $anio),
            'firma' => $this->firma->sello($empresa),
        ])->setPaper('a4')->output();

        return $this->firma->firmar($pdf, $empresa, 'Certificado de rentas y retenciones de quinta categoría');
    }

    /** Zip con el certificado de cada trabajador que tuvo planilla cerrada en el año. Devuelve la ruta temporal. */
    public function zip(Empresa $empresa, int $anio): string
    {
        $trabajadores = Trabajador::query()->where('empresa_id', $empresa->id)
            ->whereHas('lineas.periodo', fn ($q) => $q->where('anio', $anio)->where('estado', EstadoPeriodo::Cerrado))
            ->orderBy('apellido_paterno')->get();
        if ($trabajadores->isEmpty()) {
            throw new \DomainException("No hay planillas cerradas de {$anio} para emitir certificados.");
        }

        $ruta = tempnam(sys_get_temp_dir(), 'cq');
        $zip = new ZipArchive;
        if ($ruta === false || $zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el zip de certificados.');
        }
        foreach ($trabajadores as $t) {
            $zip->addFromString($this->nombre($t, $anio), $this->pdf($t, $anio));
        }
        $zip->close();

        return $ruta;
    }

    public function nombre(Trabajador $t, int $anio): string
    {
        return Str::slug("certificado-quinta-{$anio}-{$t->numero_documento}-{$t->apellido_paterno}").'.pdf';
    }
}
