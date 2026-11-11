<?php

namespace App\Services\Reportes;

use App\Domain\Planilla\ProvisionesMensuales;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoConcepto;
use App\Models\Afp;
use App\Models\CentroCosto;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaConcepto;
use App\Models\PlanillaLinea;
use App\Services\Planilla\ParametrosRepositorio;
use App\Services\Planilla\VacacionesTrabajador;
use App\Services\Trabajadores\HistorialTrabajador;
use Brick\Math\BigDecimal;
use Illuminate\Support\Collection;

/**
 * Reportes de la planilla de un periodo (empresa activa). Cada reporte es una lista de tablas con
 * columnas, filas y totales; `ExportadorReportes` las escribe en Excel o PDF.
 *
 * @phpstan-type Columna array{clave: string, titulo: string, tipo: string}
 * @phpstan-type Tabla array{titulo: string, columnas: list<Columna>, filas: list<array<string, string|int|null>>, totales: array<string, string|int|null>|null}
 * @phpstan-type Reporte array{titulo: string, tablas: list<Tabla>, avisos: list<string>}
 */
class ReportesPlanilla
{
    public const TIPOS = [
        'planilla' => 'Planilla del mes',
        'resumen' => 'Resumen',
        'centralizacion' => 'Centralización contable',
        'costo' => 'Costo laboral',
        'centros' => 'Costo por centro de costo',
    ];

    public function __construct(
        private readonly HistorialTrabajador $historial,
        private readonly ParametrosRepositorio $parametros,
        private readonly VacacionesTrabajador $vacaciones,
    ) {}

    /** @return Reporte */
    public function generar(Periodo $periodo, string $tipo): array
    {
        $lineas = $this->lineas($periodo);

        return match ($tipo) {
            'planilla' => $this->planilla($lineas),
            'resumen' => $this->resumen($periodo, $lineas),
            'centralizacion' => $this->centralizacion($lineas),
            'costo' => $this->costoLaboral($periodo, $lineas),
            'centros' => $this->costoPorCentro($periodo, $lineas),
            default => throw new \InvalidArgumentException("Reporte desconocido: {$tipo}."),
        };
    }

    /** @return Collection<int, PlanillaLinea> */
    private function lineas(Periodo $periodo): Collection
    {
        $lineas = $periodo->lineas()->whereNotNull('calculado_en')
            ->with(['trabajador.afp', 'conceptos.concepto'])->get()
            ->sortBy(fn (PlanillaLinea $l) => $l->trabajador->nombre_completo)->values();

        if ($lineas->isEmpty()) {
            throw new \DomainException("El periodo {$periodo->etiqueta()} no tiene trabajadores calculados: calcula la planilla primero.");
        }

        return $lineas;
    }

    /**
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return Reporte
     */
    private function planilla(Collection $lineas): array
    {
        // Conceptos presentes en el periodo, agrupados por tipo y ordenados por código.
        $conceptos = $lineas->flatMap(fn (PlanillaLinea $l) => $l->conceptos->pluck('concepto'))
            ->unique('id')->sortBy('codigo_plame')->values();
        $deTipo = fn (TipoConcepto ...$tipos) => $conceptos->filter(fn (Concepto $c) => in_array($c->tipo, $tipos, true))->values();
        $ingresos = $deTipo(TipoConcepto::Ingreso);
        $descuentos = $deTipo(TipoConcepto::AporteTrabajador, TipoConcepto::DescuentoTrabajador);
        $empleador = $deTipo(TipoConcepto::AporteEmpleador);

        $col = fn (Concepto $c) => ['clave' => 'c'.$c->codigo_plame, 'titulo' => "{$c->codigo_plame} {$c->nombre}", 'tipo' => 'monto'];
        $columnas = [
            ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto'],
            ['clave' => 'documento', 'titulo' => 'Documento', 'tipo' => 'texto'],
            ['clave' => 'dias', 'titulo' => 'Días lab.', 'tipo' => 'numero'],
            ['clave' => 'vacaciones', 'titulo' => 'Vac.', 'tipo' => 'numero'],
            ['clave' => 'faltas', 'titulo' => 'Faltas', 'tipo' => 'numero'],
            ...$ingresos->map($col)->all(),
            ['clave' => 'total_ingresos', 'titulo' => 'Total ingresos', 'tipo' => 'monto'],
            ...$descuentos->map($col)->all(),
            ['clave' => 'total_descuentos', 'titulo' => 'Total descuentos', 'tipo' => 'monto'],
            ['clave' => 'neto', 'titulo' => 'Neto a pagar', 'tipo' => 'monto'],
            ...$empleador->map($col)->all(),
            ['clave' => 'total_empleador', 'titulo' => 'Aportes empleador', 'tipo' => 'monto'],
        ];

        $filas = $lineas->map(function (PlanillaLinea $l) {
            $fila = [
                'trabajador' => $l->trabajador->nombre_completo,
                'documento' => "{$l->trabajador->tipo_documento->abreviatura()} {$l->trabajador->numero_documento}",
                'dias' => $l->dias_laborados,
                'vacaciones' => $l->dias_vacaciones,
                'faltas' => $l->dias_no_laborados,
                'total_ingresos' => $this->m($l->total_ingresos),
                'total_descuentos' => $this->m($l->total_descuentos),
                'neto' => $this->m($l->neto_pagar),
                'total_empleador' => $this->m($l->total_aportes_empleador),
            ];
            foreach ($l->conceptos as $c) {
                $fila['c'.$c->concepto->codigo_plame] = $this->m($c->monto_pagado);
            }

            return $fila;
        })->all();

        return [
            'titulo' => 'Planilla del mes',
            'tablas' => [['titulo' => 'Planilla', 'columnas' => $columnas, 'filas' => array_values($filas), 'totales' => $this->totales($columnas, $filas, 'trabajador', count($filas).' trabajadores')]],
            'avisos' => [],
        ];
    }

    /**
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return Reporte
     */
    private function resumen(Periodo $periodo, Collection $lineas): array
    {
        /** @var array<string, array{concepto: Concepto, total: BigDecimal, trabajadores: int}> $porConcepto */
        $porConcepto = [];
        foreach ($lineas as $l) {
            foreach ($l->conceptos as $c) {
                $codigo = $c->concepto->codigo_plame;
                $porConcepto[$codigo] ??= ['concepto' => $c->concepto, 'total' => BigDecimal::zero(), 'trabajadores' => 0];
                $porConcepto[$codigo]['total'] = $porConcepto[$codigo]['total']->plus($c->monto_pagado);
                $porConcepto[$codigo]['trabajadores']++;
            }
        }
        ksort($porConcepto);

        $columnasConceptos = [
            ['clave' => 'codigo', 'titulo' => 'Código', 'tipo' => 'texto'],
            ['clave' => 'concepto', 'titulo' => 'Concepto', 'tipo' => 'texto'],
            ['clave' => 'tipo', 'titulo' => 'Tipo', 'tipo' => 'texto'],
            ['clave' => 'trabajadores', 'titulo' => 'Trabajadores', 'tipo' => 'numero'],
            ['clave' => 'total', 'titulo' => 'Total', 'tipo' => 'monto'],
        ];
        $filasConceptos = array_values(array_map(fn (array $x) => [
            'codigo' => $x['concepto']->codigo_plame,
            'concepto' => $x['concepto']->nombre,
            'tipo' => $x['concepto']->tipo->etiqueta(),
            'trabajadores' => $x['trabajadores'],
            'total' => $this->m($x['total']),
        ], $porConcepto));

        // Lo que hay que pagar a cada entidad.
        $total = fn (array $codigos) => array_reduce($codigos, fn (BigDecimal $s, string $c) => $s->plus($porConcepto[$c]['total'] ?? BigDecimal::zero()), BigDecimal::zero());
        $pagos = [];
        $agregar = function (string $entidad, string $detalle, BigDecimal $monto) use (&$pagos) {
            if ($monto->isPositive()) {
                $pagos[] = ['entidad' => $entidad, 'detalle' => $detalle, 'monto' => $this->m($monto)];
            }
        };
        $agregar('ONP', 'Sistema Nacional de Pensiones (0607)', $total(['0607']));

        $porAfp = [];
        foreach ($lineas as $l) {
            $t = $this->historial->vistaAl($l->trabajador, $periodo->ultimoDia());
            if ($t->sistema_pension !== SistemaPension::Afp) {
                continue;
            }
            $nombre = $t->afp_id !== null ? (Afp::query()->find($t->afp_id)->nombre ?? 'AFP') : 'AFP sin asignar';
            foreach ($l->conceptos as $c) {
                if (in_array($c->concepto->codigo_plame, ['0601', '0606', '0608'], true)) {
                    $porAfp[$nombre] = ($porAfp[$nombre] ?? BigDecimal::zero())->plus($c->monto_pagado);
                }
            }
        }
        ksort($porAfp);
        foreach ($porAfp as $nombre => $monto) {
            $agregar($nombre, 'Aporte, prima y comisión (AFPnet)', $monto);
        }
        $agregar('EsSalud', 'Aporte del empleador (0804)', $total(['0804']));
        $agregar('SIS', 'Aporte microempresa (0811)', $total(['0811']));
        $agregar('SUNAT', 'Retención de renta de quinta categoría (0605)', $total(['0605']));
        $neto = $lineas->reduce(fn (BigDecimal $s, PlanillaLinea $l) => $s->plus($l->neto_pagar), BigDecimal::zero());
        $agregar('Trabajadores', 'Remuneraciones netas', $neto);

        $columnasPagos = [
            ['clave' => 'entidad', 'titulo' => 'Entidad', 'tipo' => 'texto'],
            ['clave' => 'detalle', 'titulo' => 'Detalle', 'tipo' => 'texto'],
            ['clave' => 'monto', 'titulo' => 'Monto', 'tipo' => 'monto'],
        ];

        return [
            'titulo' => 'Resumen de la planilla',
            'tablas' => [
                ['titulo' => 'Pagos del mes', 'columnas' => $columnasPagos, 'filas' => $pagos, 'totales' => $this->totales($columnasPagos, $pagos, 'entidad', 'Total')],
                ['titulo' => 'Totales por concepto', 'columnas' => $columnasConceptos, 'filas' => $filasConceptos, 'totales' => null],
            ],
            'avisos' => [],
        ];
    }

    /**
     * Asiento de centralización: cargos a gastos de personal y abonos a tributos, AFP, descuentos y neto por pagar.
     *
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return Reporte
     */
    private function centralizacion(Collection $lineas): array
    {
        /** @var array<string, BigDecimal> $debe */
        $debe = [];
        /** @var array<string, BigDecimal> $haber */
        $haber = [];
        /** @var array<string, array<string, string>> $glosa */
        $glosa = [];
        $sinCuenta = [];

        $mover = function (?string $cuenta, bool $alDebe, BigDecimal $monto, Concepto $c) use (&$debe, &$haber, &$glosa, &$sinCuenta): void {
            if ($monto->isZero()) {
                return;
            }
            if ($cuenta === null || $cuenta === '') {
                $sinCuenta[$c->codigo_plame] = "{$c->codigo_plame} {$c->nombre}";
                $cuenta = '—';
            }
            if ($alDebe) {
                $debe[$cuenta] = ($debe[$cuenta] ?? BigDecimal::zero())->plus($monto);
            } else {
                $haber[$cuenta] = ($haber[$cuenta] ?? BigDecimal::zero())->plus($monto);
            }
            $glosa[$cuenta][$c->codigo_plame] = $c->nombre;
        };

        $neto = BigDecimal::zero();
        foreach ($lineas as $l) {
            $neto = $neto->plus($l->neto_pagar);
            foreach ($l->conceptos as $pc) {
                $c = $pc->concepto;
                $monto = BigDecimal::of($pc->monto_pagado);
                if ($c->tipo === TipoConcepto::Ingreso) {
                    $mover($c->cuenta_cargo, true, $monto, $c);
                } elseif ($c->tipo === TipoConcepto::AporteEmpleador) {
                    $mover($c->cuenta_cargo, true, $monto, $c);
                    $mover($c->cuenta_abono, false, $monto, $c);
                } else {
                    $mover($c->cuenta_abono, false, $monto, $c);
                }
            }
        }
        $cuentaNeto = (string) config('planillas.cuenta_remuneraciones_por_pagar', '4111');
        $haber[$cuentaNeto] = ($haber[$cuentaNeto] ?? BigDecimal::zero())->plus($neto);
        $glosa[$cuentaNeto]['neto'] = 'Remuneraciones por pagar';

        // Primero los cargos (debe), luego los abonos; cada grupo por número de cuenta.
        $cuentas = array_map('strval', array_keys($glosa)); // "6211" llega como clave entera
        usort($cuentas, fn (string $a, string $b) => [isset($debe[$a]) ? 0 : 1, $a] <=> [isset($debe[$b]) ? 0 : 1, $b]);

        $columnas = [
            ['clave' => 'cuenta', 'titulo' => 'Cuenta', 'tipo' => 'texto'],
            ['clave' => 'glosa', 'titulo' => 'Glosa', 'tipo' => 'texto'],
            ['clave' => 'debe', 'titulo' => 'Debe', 'tipo' => 'monto'],
            ['clave' => 'haber', 'titulo' => 'Haber', 'tipo' => 'monto'],
        ];
        $filas = [];
        foreach ($cuentas as $cuenta) {
            $filas[] = [
                'cuenta' => $cuenta,
                'glosa' => implode(', ', $glosa[$cuenta]),
                'debe' => isset($debe[$cuenta]) ? $this->m($debe[$cuenta]) : null,
                'haber' => isset($haber[$cuenta]) ? $this->m($haber[$cuenta]) : null,
            ];
        }

        $avisos = $sinCuenta === [] ? [] : ['Conceptos sin cuenta contable (asígnala en Conceptos): '.implode(', ', $sinCuenta).'.'];

        return [
            'titulo' => 'Centralización contable',
            'tablas' => [['titulo' => 'Asiento de planilla', 'columnas' => $columnas, 'filas' => $filas, 'totales' => $this->totales($columnas, $filas, 'cuenta', 'Totales')]],
            'avisos' => $avisos,
        ];
    }

    /**
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return Reporte
     */
    private function costoLaboral(Periodo $periodo, Collection $lineas): array
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;
        $fecha = $periodo->ultimoDia();
        $grati = $this->parametros->gratificacionPara($empresa, $fecha->toDateString());
        $cts = $this->parametros->cts($empresa, $fecha->toDateString());
        $diasVacaciones = $this->vacaciones->diasDerecho($empresa, $fecha->toDateTimeImmutable());

        $filas = array_map(fn (array $f) => array_diff_key($f, ['centro' => true]), $this->costoPorLinea($periodo, $lineas));

        $columnas = [
            ['clave' => 'trabajador', 'titulo' => 'Trabajador', 'tipo' => 'texto'],
            ['clave' => 'ingresos', 'titulo' => 'Remuneración bruta', 'tipo' => 'monto'],
            ['clave' => 'empleador', 'titulo' => 'Aportes del empleador', 'tipo' => 'monto'],
            ['clave' => 'gratificacion', 'titulo' => 'Prov. gratificación + bonif.', 'tipo' => 'monto'],
            ['clave' => 'cts', 'titulo' => 'Prov. CTS', 'tipo' => 'monto'],
            ['clave' => 'vacaciones', 'titulo' => 'Prov. vacaciones', 'tipo' => 'monto'],
            ['clave' => 'costo', 'titulo' => 'Costo laboral', 'tipo' => 'monto'],
        ];

        return [
            'titulo' => 'Costo laboral',
            'tablas' => [['titulo' => 'Costo laboral del mes', 'columnas' => $columnas, 'filas' => $filas, 'totales' => $this->totales($columnas, $filas, 'trabajador', 'Total')]],
            'avisos' => ['Las provisiones son una estimación mensual según el régimen (gratificación 1/6, CTS 1/12, vacaciones 1/12); no son pagos del mes.'],
        ];
    }

    /**
     * Costo de cada línea (remuneración, aportes del empleador y provisiones), con su centro de costo.
     *
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return list<array<string, string|int|null>>
     */
    private function costoPorLinea(Periodo $periodo, Collection $lineas): array
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;
        $fecha = $periodo->ultimoDia();
        $grati = $this->parametros->gratificacionPara($empresa, $fecha->toDateString());
        $cts = $this->parametros->cts($empresa, $fecha->toDateString());
        $diasVacaciones = $this->vacaciones->diasDerecho($empresa, $fecha->toDateTimeImmutable());

        return array_values($lineas->map(function (PlanillaLinea $l) use ($periodo, $grati, $cts, $diasVacaciones) {
            $t = $this->historial->vistaAl($l->trabajador, $periodo->ultimoDia());
            $asignacion = $l->conceptos->first(fn (PlanillaConcepto $c) => $c->concepto->codigo_plame === '0201')->monto_pagado ?? '0';
            $p = ProvisionesMensuales::calcular(
                (string) $t->remuneracion_basica,
                (string) $asignacion,
                $grati->factorRegimen,
                $t->seguro_salud === SeguroSalud::Essalud ? $grati->tasaBonificacion : '0',
                $cts->factorRegimen,
                $diasVacaciones,
            );
            $costo = BigDecimal::of($l->total_ingresos)->plus($l->total_aportes_empleador)->plus($p['total']);

            return [
                'centro' => $l->centro_costo_id,
                'trabajador' => $t->nombre_completo,
                'ingresos' => $this->m($l->total_ingresos),
                'empleador' => $this->m($l->total_aportes_empleador),
                'gratificacion' => $this->m(BigDecimal::of($p['gratificacion'])->plus($p['bonificacion'])),
                'cts' => $p['cts'],
                'vacaciones' => $p['vacaciones'],
                'costo' => $this->m($costo),
            ];
        })->all());
    }

    /**
     * Costo laboral agrupado por el centro de costo que tenía cada trabajador en el mes.
     *
     * @param  Collection<int, PlanillaLinea>  $lineas
     * @return Reporte
     */
    private function costoPorCentro(Periodo $periodo, Collection $lineas): array
    {
        $centros = CentroCosto::query()->where('empresa_id', $periodo->empresa_id)->get()->keyBy('id');
        $montos = ['ingresos', 'empleador', 'gratificacion', 'cts', 'vacaciones', 'costo'];
        $grupos = [];

        foreach ($this->costoPorLinea($periodo, $lineas) as $f) {
            $centro = $centros->get((int) $f['centro']);
            $clave = $centro === null ? '~' : $centro->codigo;
            $grupos[$clave] ??= [
                'centro' => $centro === null ? 'Sin centro de costo' : "{$centro->codigo} · {$centro->nombre}",
                'trabajadores' => 0,
                ...array_fill_keys($montos, BigDecimal::zero()),
            ];
            $grupos[$clave]['trabajadores']++;
            foreach ($montos as $m) {
                $grupos[$clave][$m] = $grupos[$clave][$m]->plus((string) $f[$m]);
            }
        }
        ksort($grupos);

        $filas = array_values(array_map(fn (array $g) => [
            'centro' => $g['centro'],
            'trabajadores' => $g['trabajadores'],
            ...array_map(fn (BigDecimal $v) => $this->m($v), array_intersect_key($g, array_flip($montos))),
        ], $grupos));

        $columnas = [
            ['clave' => 'centro', 'titulo' => 'Centro de costo', 'tipo' => 'texto'],
            ['clave' => 'trabajadores', 'titulo' => 'Trabajadores', 'tipo' => 'numero'],
            ['clave' => 'ingresos', 'titulo' => 'Remuneración bruta', 'tipo' => 'monto'],
            ['clave' => 'empleador', 'titulo' => 'Aportes del empleador', 'tipo' => 'monto'],
            ['clave' => 'gratificacion', 'titulo' => 'Prov. gratificación + bonif.', 'tipo' => 'monto'],
            ['clave' => 'cts', 'titulo' => 'Prov. CTS', 'tipo' => 'monto'],
            ['clave' => 'vacaciones', 'titulo' => 'Prov. vacaciones', 'tipo' => 'monto'],
            ['clave' => 'costo', 'titulo' => 'Costo laboral', 'tipo' => 'monto'],
        ];

        $avisos = ['Agrupado por el centro de costo vigente en el mes (el de la ficha al calcular la planilla).'];
        if (isset($grupos['~'])) {
            $avisos[] = 'Hay trabajadores sin centro de costo: asígnalo en su ficha y vuelve a calcular la planilla.';
        }

        return [
            'titulo' => 'Costo por centro de costo',
            'tablas' => [['titulo' => 'Costo laboral por centro de costo', 'columnas' => $columnas, 'filas' => $filas, 'totales' => $this->totales($columnas, $filas, 'centro', 'Total')]],
            'avisos' => $avisos,
        ];
    }

    /**
     * Fila de totales: suma (sin float) de las columnas de monto y número.
     *
     * @param  list<array{clave: string, titulo: string, tipo: string}>  $columnas
     * @param  array<int, array<string, string|int|null>>  $filas
     * @return array<string, string|int|null>
     */
    private function totales(array $columnas, array $filas, string $etiquetaEn, string $etiqueta): array
    {
        $totales = [$etiquetaEn => $etiqueta];
        foreach ($columnas as $c) {
            if ($c['tipo'] === 'monto') {
                $totales[$c['clave']] = $this->m(array_reduce($filas, fn (BigDecimal $s, array $f) => $s->plus((string) ($f[$c['clave']] ?? '0')), BigDecimal::zero()));
            } elseif ($c['tipo'] === 'numero') {
                $totales[$c['clave']] = array_sum(array_map(fn (array $f) => (int) ($f[$c['clave']] ?? 0), $filas));
            }
        }

        return $totales;
    }

    private function m(BigDecimal|string|null $monto): string
    {
        return (string) BigDecimal::of((string) ($monto ?? '0'))->toScale(2);
    }
}
