<?php

namespace App\Services\Planilla;

use App\Models\EntidadFinanciera;
use App\Models\LiquidacionCts;
use App\Models\LiquidacionCtsLinea;
use App\Services\Empresas\FirmaDigitalPdf;
use App\Services\Empresas\LogoEmpresa;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Constancia de depósito de CTS (art. 29 del D.S. 001-97-TR): el empleador la entrega al trabajador
 * dentro de los 5 días hábiles siguientes al depósito. Una por hoja A4.
 */
class ConstanciaCts
{
    /** @param  Collection<int, LiquidacionCtsLinea>  $lineas */
    public function pdf(LiquidacionCts $liquidacion, Collection $lineas): string
    {
        $liquidacion->loadMissing('empresa');
        $entidades = EntidadFinanciera::query()->pluck('nombre', 'codigo');

        $firma = app(FirmaDigitalPdf::class);
        $firmar = $liquidacion->estaCerrada();

        $pdf = Pdf::loadView('pdf.constancia-cts', [
            'liquidacion' => $liquidacion,
            'empresa' => $liquidacion->empresa,
            'logo' => app(LogoEmpresa::class)->dataUri($liquidacion->empresa),
            'lineas' => $lineas->each(fn (LiquidacionCtsLinea $l) => $l->loadMissing('trabajador')),
            'entidades' => $entidades,
            'borrador' => ! $liquidacion->estaCerrada(),
            'firma' => $firmar ? $firma->sello($liquidacion->empresa) : null,
        ])->setPaper('a4')->output();

        return $firmar ? $firma->firmar($pdf, $liquidacion->empresa, 'Constancia de depósito de CTS') : $pdf;
    }

    /** Zip con una constancia por trabajador. Devuelve la ruta temporal. */
    public function zip(LiquidacionCts $liquidacion): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'cts');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el zip de constancias.');
        }
        foreach ($this->lineas($liquidacion) as $linea) {
            $zip->addFromString($this->nombreArchivo($liquidacion, $linea), $this->pdf($liquidacion, collect([$linea])));
        }
        $zip->close();

        return $ruta;
    }

    /** @return Collection<int, LiquidacionCtsLinea> */
    public function lineas(LiquidacionCts $liquidacion): Collection
    {
        $lineas = $liquidacion->lineas()->with('trabajador')->get()
            ->sortBy(fn (LiquidacionCtsLinea $l) => $l->trabajador->nombre_completo)->values();

        if ($lineas->isEmpty()) {
            throw new \DomainException('Calcula la CTS antes de generar las constancias.');
        }

        return $lineas;
    }

    /** "CTS 2027-05 70000011 Vargas Leon Sofia.pdf" */
    public function nombreArchivo(LiquidacionCts $liquidacion, LiquidacionCtsLinea $linea): string
    {
        $t = $linea->trabajador;
        $nombre = Str::of(Str::ascii("{$t->apellido_paterno} {$t->apellido_materno} {$t->nombres}"))
            ->replaceMatches('/[^A-Za-z0-9 ]/', '')->squish();

        return sprintf('CTS %04d-%02d %s %s.pdf', $liquidacion->anio, $liquidacion->mes, $t->numero_documento, $nombre);
    }

    public function nombreLote(LiquidacionCts $liquidacion, string $extension): string
    {
        return sprintf('Constancias CTS %04d-%02d %s.%s', $liquidacion->anio, $liquidacion->mes, $liquidacion->empresa->ruc ?? 'empresa', $extension);
    }
}
