<?php

namespace App\Services\Sunat;

use App\Domain\Sunat\Plame\GeneradorJor;
use App\Domain\Sunat\Plame\GeneradorRem;
use App\Domain\Sunat\Plame\GeneradorSnl;
use App\Domain\Sunat\Plame\GeneradorToc;
use App\Domain\Sunat\Plame\NombreArchivoPlame;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\TipoSuspension;
use App\Models\User;
use App\Services\Planilla\NovedadesPlanilla;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Archivos de importación del PLAME (.rem, .jor, .snl, .toc) de un periodo cerrado.
 * Cada descarga deja una constancia en `exportaciones` con el hash de cada archivo.
 */
class ExportacionPlame
{
    /** ⚠ Jornada ordinaria diaria asumida para el .jor (48 h semanales). */
    private const HORAS_DIA = 8;

    public function __construct(
        private readonly GeneradorRem $rem,
        private readonly GeneradorJor $jor,
        private readonly GeneradorSnl $snl,
        private readonly GeneradorToc $toc,
    ) {}

    /**
     * Archivos del periodo: nombre oficial => contenido. Los vacíos (p. ej. .snl sin suspensiones) se omiten.
     *
     * @return array<string, string>
     */
    public function archivos(Periodo $periodo): array
    {
        if (! $periodo->estaCerrado()) {
            throw new \DomainException("El periodo {$periodo->etiqueta()} no está cerrado: cierra la planilla antes de exportar al PLAME.");
        }

        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;
        $lineas = $periodo->lineas()->whereNotNull('calculado_en')
            ->with(['trabajador', 'conceptos.concepto'])->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->numero_documento)->values();

        if ($lineas->isEmpty()) {
            throw new \DomainException("El periodo {$periodo->etiqueta()} no tiene trabajadores calculados.");
        }

        $codigoFalta = TipoSuspension::codigoFalta();
        $doc = fn (PlanillaLinea $l) => [
            'tipo_documento' => $l->trabajador->tipo_documento->value,
            'numero_documento' => $l->trabajador->numero_documento,
        ];

        $contenidos = [
            'rem' => $this->rem->generar($lineas->map(fn (PlanillaLinea $l) => [...$doc($l), 'conceptos' => $this->conceptos($l)])->all()),
            'jor' => $this->jor->generar($lineas->map(fn (PlanillaLinea $l) => [...$doc($l), ...$this->jornada($l)])->all()),
            'snl' => $this->snl->generar($lineas->map(fn (PlanillaLinea $l) => [...$doc($l), 'suspensiones' => [
                $codigoFalta => $l->dias_no_laborados,
                $l->tipo_subsidio ?? NovedadesPlanilla::SUBSIDIO_POR_DEFECTO => $l->dias_subsidiados,
            ]])->all()),
            'toc' => $this->toc->generar($lineas->map($doc)->all()),
        ];

        $archivos = [];
        foreach ($contenidos as $extension => $contenido) {
            if ($contenido !== '') {
                $archivos[NombreArchivoPlame::para($empresa->ruc, $periodo->anio, $periodo->mes, $extension)] = $contenido;
            }
        }

        return $archivos;
    }

    /** Zip con los archivos de un periodo. Devuelve la ruta temporal (borrarla después de enviarla). */
    public function zipPeriodo(Periodo $periodo, User $usuario): string
    {
        $archivos = $this->archivos($periodo);
        $this->registrar($periodo, $archivos, $usuario);

        return $this->zip($archivos);
    }

    /**
     * Zip con una carpeta por empresa («RUC Razón social/») con todos los periodos cerrados del mes.
     *
     * @param  Collection<int, Periodo>  $periodos
     */
    public function zipVarios(Collection $periodos, User $usuario): string
    {
        $todos = [];
        foreach ($periodos as $periodo) {
            $archivos = $this->archivos($periodo);
            $this->registrar($periodo, $archivos, $usuario);

            /** @var Empresa $empresa */
            $empresa = $periodo->empresa;
            $carpeta = trim(Str::of(Str::ascii("{$empresa->ruc} {$empresa->razon_social}"))->replaceMatches('/[^A-Za-z0-9 .-]/', '')->squish());
            foreach ($archivos as $nombre => $contenido) {
                $todos["{$carpeta}/{$nombre}"] = $contenido;
            }
        }

        return $this->zip($todos);
    }

    public function nombreZip(int $anio, int $mes, ?string $ruc = null): string
    {
        return sprintf('PLAME %04d-%02d%s.zip', $anio, $mes, $ruc !== null ? " {$ruc}" : ' todas las empresas');
    }

    /**
     * Conceptos del .rem: código => monto pagado (los montos en cero no se declaran ⚠).
     *
     * @return array<string, string>
     */
    private function conceptos(PlanillaLinea $linea): array
    {
        return $linea->conceptos
            ->filter(fn (PlanillaConcepto $c) => ! BigDecimal::of($c->monto_pagado)->isZero())
            ->sortBy(fn (PlanillaConcepto $c) => $c->concepto->codigo_plame)
            ->mapWithKeys(fn (PlanillaConcepto $c) => [$c->concepto->codigo_plame => (string) BigDecimal::of($c->monto_pagado)->toScale(2)])
            ->all();
    }

    /** @return array{horas_ordinarias: int, minutos_ordinarios: int, horas_extra: int, minutos_extra: int} */
    private function jornada(PlanillaLinea $linea): array
    {
        $extra = BigDecimal::of($linea->horas_extra_25)->plus($linea->horas_extra_35);
        $horas = $extra->toScale(0, RoundingMode::Down);
        $minutos = $extra->minus($horas)->multipliedBy(60)->toScale(0, RoundingMode::HalfUp);

        return [
            'horas_ordinarias' => $linea->dias_laborados * self::HORAS_DIA,
            'minutos_ordinarios' => 0,
            'horas_extra' => $horas->toInt(),
            'minutos_extra' => $minutos->toInt(),
        ];
    }

    /** @param  array<string, string>  $archivos */
    private function registrar(Periodo $periodo, array $archivos, User $usuario): void
    {
        Exportacion::query()->create([
            'tipo' => Exportacion::PLAME,
            'empresa_id' => $periodo->empresa_id,
            'periodo_id' => $periodo->id,
            'anio' => $periodo->anio,
            'mes' => $periodo->mes,
            'archivos' => array_map(
                fn (string $nombre, string $contenido) => ['nombre' => $nombre, 'sha256' => hash('sha256', $contenido), 'bytes' => strlen($contenido)],
                array_keys($archivos),
                $archivos,
            ),
            'user_id' => $usuario->id,
        ]);
    }

    /** @param  array<string, string>  $archivos */
    private function zip(array $archivos): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'plame');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el zip del PLAME.');
        }
        foreach ($archivos as $nombre => $contenido) {
            $zip->addFromString($nombre, $contenido);
        }
        $zip->close();

        return $ruta;
    }
}
