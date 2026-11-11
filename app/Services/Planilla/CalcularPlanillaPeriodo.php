<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraLiquidacion;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\CalculadoraVacaciones;
use App\Domain\Planilla\DatosTrabajador;
use App\Domain\Planilla\EntradaPlanilla;
use App\Domain\Planilla\IngresoAdicional;
use App\Domain\Planilla\ResultadoGratificacion;
use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Enums\TipoConcepto;
use App\Models\Concepto;
use App\Models\Liquidacion;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;

/**
 * Calcula y guarda la planilla de todos los trabajadores de un periodo.
 * Une la base de datos (modelos) con el motor puro (CalculadoraPlanilla).
 */
class CalcularPlanillaPeriodo
{
    /** Tabla 22: remuneración vacacional (⚠ verificar). */
    public const REMUNERACION_VACACIONAL = '0118';

    public function __construct(
        private readonly CalculadoraPlanilla $calculadora,
        private readonly ParametrosRepositorio $parametros,
        private readonly HistorialTrabajador $historial,
        private readonly VerificadorCalculo $verificador,
        private readonly GratificacionPeriodo $gratificacion,
        private readonly VacacionesTrabajador $vacaciones,
        private readonly LiquidacionesBeneficios $liquidaciones,
        private readonly RetencionQuinta $quinta,
        private readonly PrestamosTrabajador $prestamos,
    ) {}

    /**
     * Calcula todo el periodo o nada: si falta algún dato, lanza ProblemasDeCalculo con la lista completa.
     *
     * @return array{trabajadores: int, neto: string}
     *
     * @throws ProblemasDeCalculo
     */
    public function ejecutar(Periodo $periodo): array
    {
        if ($periodo->estaCerrado()) {
            throw new \DomainException('El periodo está cerrado. Reábrelo para recalcular.');
        }

        $problemas = $this->verificador->problemas($periodo);
        if ($problemas !== []) {
            throw new ProblemasDeCalculo($problemas);
        }

        $parametros = $this->parametros->delPeriodo($periodo);
        $conceptos = Concepto::query()->pluck('id', 'codigo_plame');

        DB::transaction(function () use ($periodo, $parametros, $conceptos) {
            $periodo->load('lineas.trabajador', 'lineas.novedades.concepto');
            $gratificaciones = $this->gratificacion->calcular($periodo);
            $liquidaciones = $this->liquidaciones->delPeriodo($periodo);
            $this->quinta->preparar($periodo);
            $cuotas = $this->prestamos->cuotasDelPeriodo($periodo);

            foreach ($periodo->lineas as $linea) {
                $datos = $this->datosTrabajador($linea, $periodo);
                $entrada = $this->entrada($linea, $gratificaciones[$linea->id] ?? null, $this->remuneracionVacacional($linea, $periodo, $datos), $liquidaciones->get($linea->trabajador_id), $cuotas[$linea->trabajador_id] ?? null);
                $resultado = $this->calculadora->calcular($datos, $entrada, $parametros);

                // Quinta categoría: sobre los ingresos ya calculados; entra como descuento 0605 y se recalcula el neto.
                // Si el usuario registró el 0605 como novedad, se respeta ese monto.
                $detalleQuinta = null;
                if (! array_key_exists(RetencionQuinta::CODIGO, $entrada->descuentos)) {
                    $quinta = $this->quinta->calcular($linea, $datos, $resultado);
                    $detalleQuinta = $quinta->detalle();
                    if (BigDecimal::of($quinta->retencion)->isPositive()) {
                        $resultado = $this->calculadora->calcular($datos, $this->conDescuento($entrada, RetencionQuinta::CODIGO, $quinta->retencion), $parametros);
                    }
                }

                $linea->conceptos()->delete();
                foreach ($resultado->conceptos() as $codigo => $monto) {
                    $linea->conceptos()->create([
                        'concepto_id' => $conceptos[$codigo] ?? throw new \RuntimeException("Concepto {$codigo} no existe."),
                        'monto_devengado' => $monto,
                        'monto_pagado' => $monto,
                    ]);
                }

                $linea->update([
                    'total_ingresos' => $resultado->totalIngresos,
                    'total_descuentos' => $resultado->totalDescuentos,
                    'total_aportes_empleador' => $resultado->totalAportesEmpleador,
                    'neto_pagar' => $resultado->netoPagar,
                    'centro_costo_id' => $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia())->centro_costo_id,
                    'detalle_quinta' => $detalleQuinta,
                    'calculado_en' => now(),
                ]);
            }

            $periodo->update(['estado' => EstadoPeriodo::Calculado]);
        });

        $neto = $periodo->lineas()->pluck('neto_pagar')
            ->reduce(fn (BigDecimal $suma, $n) => $suma->plus((string) $n), BigDecimal::zero());

        return [
            'trabajadores' => $periodo->lineas()->count(),
            'neto' => (string) $neto->toScale(2),
        ];
    }

    private function conDescuento(EntradaPlanilla $e, string $codigo, string $monto): EntradaPlanilla
    {
        return new EntradaPlanilla(
            diasLaborados: $e->diasLaborados,
            horasExtra25: $e->horasExtra25,
            horasExtra35: $e->horasExtra35,
            ingresosAdicionales: $e->ingresosAdicionales,
            descuentos: [...$e->descuentos, $codigo => $monto],
            diasVacaciones: $e->diasVacaciones,
        );
    }

    private function datosTrabajador(PlanillaLinea $linea, Periodo $periodo): DatosTrabajador
    {
        // Valores vigentes al cierre del mes (regla 3: un periodo antiguo se recalcula igual).
        $t = $this->historial->vistaAl($linea->trabajador, $periodo->ultimoDia());

        return new DatosTrabajador(
            remuneracionBasica: (string) $t->remuneracion_basica,
            asignacionFamiliar: $t->asignacion_familiar,
            sistemaPension: $t->sistema_pension,
            seguroSalud: $t->seguro_salud,
            tasaAfp: $this->parametros->tasaAfp($t, $periodo),
            agrario: $periodo->empresa?->regimen_laboral === RegimenLaboral::Agrario,
            prorrateoAgrario: $t->prorrateo_agrario,
        );
    }

    /** 0118 = (básico + promedio de horas extra) / 30 × días de vacaciones del mes. */
    private function remuneracionVacacional(PlanillaLinea $linea, Periodo $periodo, DatosTrabajador $datos): ?string
    {
        if ($linea->dias_vacaciones <= 0) {
            return null;
        }

        return CalculadoraVacaciones::remuneracion($datos->remuneracionBasica, $this->vacaciones->promedioVariables($linea, $periodo), $linea->dias_vacaciones);
    }

    private function entrada(PlanillaLinea $linea, ?ResultadoGratificacion $gratificacion, ?string $vacacional = null, ?Liquidacion $liquidacion = null, ?string $cuotaPrestamo = null): EntradaPlanilla
    {
        $ingresos = [];

        // Remuneración vacacional: afecta a pensión y EsSalud como el sueldo.
        if ($vacacional !== null) {
            $ingresos[self::REMUNERACION_VACACIONAL] = new IngresoAdicional($vacacional, true, true);
        }

        // Gratificación y bonificación extraordinaria: no afectas a pensión ni a EsSalud.
        if ($gratificacion?->corresponde()) {
            $ingresos[CalculadoraGratificacion::GRATIFICACION] = new IngresoAdicional($gratificacion->gratificacion, false, false);
            $ingresos[CalculadoraGratificacion::BONIFICACION] = new IngresoAdicional($gratificacion->bonificacion, false, false);
        }

        // Liquidación de beneficios del cese en este mes: solo las vacaciones son remuneración (afectas).
        // Si un código ya está (p. ej. 0118 por días gozados del mes), se suma.
        foreach ($liquidacion?->conceptos() ?? [] as $codigo => $monto) {
            $previo = $ingresos[$codigo] ?? null;
            $afecto = in_array($codigo, CalculadoraLiquidacion::AFECTOS, true);
            $ingresos[$codigo] = new IngresoAdicional(
                $previo === null ? $monto : (string) BigDecimal::of($previo->monto)->plus($monto)->toScale(2),
                $afecto,
                $afecto,
            );
        }

        $descuentos = [];
        foreach ($linea->novedades as $novedad) {
            $c = $novedad->concepto;
            if ($c->tipo === TipoConcepto::Ingreso) {
                $ingresos[$c->codigo_plame] = new IngresoAdicional((string) $novedad->monto, $c->afecto_afp || $c->afecto_onp, $c->afecto_essalud);
            } elseif ($c->tipo === TipoConcepto::DescuentoTrabajador) {
                $descuentos[$c->codigo_plame] = (string) $novedad->monto;
            }
        }

        // Cuotas de préstamos del mes (0706); si se registró el 0706 a mano en la grilla, se usa ese monto.
        if ($cuotaPrestamo !== null && ! array_key_exists(PrestamosTrabajador::CODIGO, $descuentos)) {
            $descuentos[PrestamosTrabajador::CODIGO] = $cuotaPrestamo;
        }

        return new EntradaPlanilla(
            diasLaborados: $linea->dias_laborados,
            horasExtra25: (string) $linea->horas_extra_25,
            horasExtra35: (string) $linea->horas_extra_35,
            ingresosAdicionales: $ingresos,
            descuentos: $descuentos,
            diasVacaciones: $linea->dias_vacaciones,
        );
    }
}
