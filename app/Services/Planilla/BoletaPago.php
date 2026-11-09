<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\MontoEnLetras;
use App\Enums\EstadoPeriodo;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Boletas de pago en PDF (D.S. 001-98-TR): una por hoja A4.
 * Solo de trabajadores calculados. Si el periodo no está cerrado, llevan la marca «BORRADOR».
 */
class BoletaPago
{
    public function __construct(private readonly DetalleLinea $detalle) {}

    /** PDF de un trabajador (contenido binario). */
    public function pdf(Periodo $periodo, PlanillaLinea $linea): string
    {
        $this->exigirCalculada($periodo, $linea);

        return $this->render($periodo, collect([$linea]));
    }

    /** Un solo PDF con las boletas de todos los trabajadores calculados (para imprimir). */
    public function pdfLote(Periodo $periodo): string
    {
        return $this->render($periodo, $this->lineas($periodo));
    }

    /**
     * Zip con un PDF por trabajador. Devuelve la ruta de un archivo temporal (borrarlo después de enviarlo).
     */
    public function zip(Periodo $periodo): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'boletas');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el zip de boletas.');
        }

        foreach ($this->lineas($periodo) as $linea) {
            $zip->addFromString($this->nombreArchivo($periodo, $linea), $this->render($periodo, collect([$linea])));
        }
        $zip->close();

        return $ruta;
    }

    /** "2026-11 70000011 Vargas Leon Sofia.pdf" (sin tildes ni símbolos). */
    public function nombreArchivo(Periodo $periodo, PlanillaLinea $linea): string
    {
        $t = $linea->trabajador;
        $nombre = Str::of(Str::ascii("{$t->apellido_paterno} {$t->apellido_materno} {$t->nombres}"))
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')
            ->squish();

        return sprintf('%04d-%02d %s %s.pdf', $periodo->anio, $periodo->mes, $t->numero_documento, $nombre);
    }

    public function nombreLote(Periodo $periodo, string $extension): string
    {
        $ruc = $periodo->empresa->ruc ?? 'empresa';

        return sprintf('Boletas %04d-%02d %s.%s', $periodo->anio, $periodo->mes, $ruc, $extension);
    }

    /** @return Collection<int, PlanillaLinea> */
    public function lineas(Periodo $periodo): Collection
    {
        if ($periodo->estado === EstadoPeriodo::Abierto && ! $periodo->lineas()->whereNotNull('calculado_en')->exists()) {
            throw new \DomainException('Calcula la planilla antes de generar las boletas.');
        }

        $lineas = $periodo->lineas()->whereNotNull('calculado_en')->with('trabajador')->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)
            ->values();

        if ($lineas->isEmpty()) {
            throw new \DomainException('No hay trabajadores calculados en este periodo.');
        }

        return $lineas;
    }

    private function exigirCalculada(Periodo $periodo, PlanillaLinea $linea): void
    {
        if ($linea->calculado_en === null) {
            throw new \DomainException('Este trabajador aún no está calculado: calcula la planilla para generar su boleta.');
        }
    }

    /** @param  Collection<int, PlanillaLinea>  $lineas */
    private function render(Periodo $periodo, Collection $lineas): string
    {
        return Pdf::loadView('pdf.boleta', $this->vista($periodo, $lineas))->setPaper('a4')->output();
    }

    /**
     * Datos que recibe la plantilla `pdf.boleta`.
     *
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return array<string, mixed>
     */
    public function vista(Periodo $periodo, Collection $lineas): array
    {
        $periodo->loadMissing('empresa');

        $boletas = $lineas->map(function (PlanillaLinea $linea) use ($periodo) {
            $datos = $this->detalle->datos($periodo, $linea);
            $neto = (string) $datos['linea']['neto_pagar'];

            return [
                ...$datos,
                // Descuentos al trabajador: aportes (ONP/AFP) + otros descuentos.
                'descuentos' => [...$datos['grupos']['aportes_trabajador']['conceptos'], ...$datos['grupos']['descuentos']['conceptos']],
                'neto_letras' => MontoEnLetras::soles($neto),
            ];
        })->all();

        return [
            'empresa' => $periodo->empresa,
            'periodo' => $periodo,
            'borrador' => $periodo->estado !== EstadoPeriodo::Cerrado,
            'boletas' => $boletas,
        ];
    }
}
