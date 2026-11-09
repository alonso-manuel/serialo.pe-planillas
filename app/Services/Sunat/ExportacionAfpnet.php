<?php

namespace App\Services\Sunat;

use App\Domain\Afp\GeneradorAfpnet;
use App\Enums\SistemaPension;
use App\Enums\TipoConcepto;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Exportacion;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Models\User;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;
use ZipArchive;

/**
 * Archivo de planilla para AFPnet (texto de ancho fijo) de un periodo cerrado: una línea por trabajador
 * en AFP, todas las AFP en el mismo archivo (AFPnet arma una planilla por AFP). Deja constancia con hash.
 */
class ExportacionAfpnet
{
    public function __construct(
        private readonly GeneradorAfpnet $generador,
        private readonly HistorialTrabajador $historial,
    ) {}

    /** @return array{nombre: string, contenido: string, trabajadores: int} */
    public function archivo(Periodo $periodo): array
    {
        if (! $periodo->estaCerrado()) {
            throw new \DomainException("El periodo {$periodo->etiqueta()} no está cerrado: cierra la planilla antes de exportar a AFPnet.");
        }

        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;
        $codigosAfp = Afp::query()->pluck('codigo_afpnet', 'id');

        $filas = [];
        $lineas = $periodo->lineas()->whereNotNull('calculado_en')->with(['trabajador', 'conceptos.concepto'])->get();
        foreach ($lineas as $linea) {
            // Datos vigentes en el mes (sistema de pensión, AFP y CUSPP según el historial).
            $t = $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia());
            if ($t->sistema_pension !== SistemaPension::Afp) {
                continue;
            }

            $remuneracion = $this->remuneracionAsegurable($linea);

            $filas[] = [
                'cuspp' => $t->cuspp,
                'tipo_documento' => $t->tipo_documento->codigoAfpnet(),
                'numero_documento' => $t->numero_documento,
                'apellido_paterno' => $t->apellido_paterno,
                'apellido_materno' => $t->apellido_materno,
                'nombres' => $t->nombres,
                'inicia' => $t->fecha_ingreso->year === $periodo->anio && $t->fecha_ingreso->month === $periodo->mes,
                'cesa' => $t->fecha_cese !== null && $t->fecha_cese->year === $periodo->anio && $t->fecha_cese->month === $periodo->mes,
                'excepcion' => $remuneracion->isZero() ? $this->excepcion($linea) : null,
                'remuneracion' => (string) $remuneracion,
                'afp' => $t->afp_id !== null ? $codigosAfp->get($t->afp_id) : null,
            ];
        }

        if ($filas === []) {
            throw new \DomainException("En {$periodo->etiqueta()} no hay trabajadores en AFP: no corresponde presentar planilla en AFPnet.");
        }

        usort($filas, fn (array $a, array $b) => strcmp($a['numero_documento'], $b['numero_documento']));

        return [
            'nombre' => sprintf('AFPNET %04d-%02d %s.txt', $periodo->anio, $periodo->mes, $empresa->ruc),
            'contenido' => $this->generador->generar($filas),
            'trabajadores' => count($filas),
        ];
    }

    /** @return array{nombre: string, contenido: string} */
    public function descargar(Periodo $periodo, User $usuario): array
    {
        $archivo = $this->archivo($periodo);
        Exportacion::registrar(Exportacion::AFPNET, $periodo, [$archivo['nombre'] => $archivo['contenido']], $usuario);

        return $archivo;
    }

    /**
     * Zip con el TXT de cada empresa del mes. Las que no tienen trabajadores en AFP se omiten.
     *
     * @param  Collection<int, Periodo>  $periodos
     * @return array{ruta: string, archivos: int}
     */
    public function zipVarios(Collection $periodos, User $usuario): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'afpnet');
        if ($ruta === false) {
            throw new \RuntimeException('No se pudo crear el archivo temporal.');
        }

        $zip = new ZipArchive;
        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo crear el zip de AFPnet.');
        }

        $archivos = 0;
        foreach ($periodos as $periodo) {
            try {
                $archivo = $this->descargar($periodo, $usuario);
            } catch (\DomainException) {
                continue; // sin trabajadores en AFP
            }
            $zip->addFromString($archivo['nombre'], $archivo['contenido']);
            $archivos++;
        }
        $zip->close();

        if ($archivos === 0) {
            @unlink($ruta);
            throw new \DomainException('Ninguna empresa tiene trabajadores en AFP en este mes.');
        }

        return ['ruta' => $ruta, 'archivos' => $archivos];
    }

    public function nombreZip(int $anio, int $mes): string
    {
        return sprintf('AFPNET %04d-%02d todas las empresas.zip', $anio, $mes);
    }

    /** Suma de los ingresos afectos a AFP: la misma base con la que se calcularon los aportes. */
    private function remuneracionAsegurable(PlanillaLinea $linea): BigDecimal
    {
        return $linea->conceptos
            ->filter(fn (PlanillaConcepto $c) => $c->concepto->tipo === TipoConcepto::Ingreso && $c->concepto->afecto_afp)
            ->reduce(fn (BigDecimal $s, PlanillaConcepto $c) => $s->plus($c->monto_pagado), BigDecimal::zero())
            ->toScale(2);
    }

    /**
     * Motivo por el que no aporta en un mes sin remuneración:
     * U subsidio pagado por EsSalud, L licencia sin goce, O otro motivo.
     */
    private function excepcion(PlanillaLinea $linea): string
    {
        return match (true) {
            $linea->dias_subsidiados > 0 && $linea->dias_subsidiados >= $linea->dias_licencia => 'U',
            $linea->dias_licencia > 0 => 'L',
            default => 'O',
        };
    }
}
