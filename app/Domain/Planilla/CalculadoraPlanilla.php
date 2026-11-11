<?php

namespace App\Domain\Planilla;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * Motor de cálculo de la planilla mensual (régimen general, REMYPE y agrario).
 *
 * Es una clase PURA: no lee la base de datos ni la hora actual. Todo lo que necesita
 * entra por parámetros, por eso se prueba con tests unitarios sin base de datos.
 *
 * IMPORTANTE: las reglas de abajo son la versión inicial y DEBEN validarse con un contador
 * y con planillas reales antes de vender el sistema (ver docs/reglas-calculo.md).
 */
final class CalculadoraPlanilla
{
    // Códigos de la tabla 22 del PLAME (verificar contra la tabla oficial vigente).
    public const BASICO = '0121';

    public const ASIGNACION_FAMILIAR = '0201';

    public const HORA_EXTRA_25 = '0105';

    public const HORA_EXTRA_35 = '0106';

    /** Tabla 22: comisiones o destajo (remuneración principal imprecisa) y comisiones eventuales. */
    public const COMISIONES = '0103';

    public const COMISIONES_EVENTUALES = '0104';

    /**
     * Remuneraciones variables que entran al promedio de gratificación, CTS y vacaciones cuando son regulares
     * (percibidas en al menos 3 de los últimos 6 meses): horas extra y comisiones ⚠.
     */
    public const VARIABLES = [self::HORA_EXTRA_25, self::HORA_EXTRA_35, self::COMISIONES, self::COMISIONES_EVENTUALES];

    public const AFP_COMISION = '0601';

    public const AFP_PRIMA = '0606';

    public const ONP = '0607';

    public const AFP_APORTE = '0608';

    public const ESSALUD = '0804';

    public const SIS = '0811';

    /** Régimen agrario (Ley 31110): BETA, gratificación y CTS prorrateadas (tabla 22). */
    public const BETA = '0314';

    public const GRATIFICACION_AGRARIA = '0406';

    public const BONIFICACION_AGRARIA = '0312';

    public const CTS_AGRARIA = '0904';

    /** Remuneración vacacional: entra a la base del prorrateo agrario. */
    public const REMUNERACION_VACACIONAL = '0118';

    private const DIAS_MES = 30;

    private const HORAS_DIA = 8;

    public function calcular(DatosTrabajador $t, EntradaPlanilla $e, ParametrosPeriodo $p): ResultadoPlanilla
    {
        $agrario = $t->agrario ? ($p->agrario ?? throw new \InvalidArgumentException('Faltan los parámetros del régimen agrario para el periodo.')) : null;
        // Días que computan para la BETA y la asignación familiar agraria: laborados y de vacaciones (art. 9.5 y 15.1 del reglamento).
        $diasComputables = min(self::DIAS_MES, $e->diasLaborados + $e->diasVacaciones);

        $ingresos = [];
        $baseAfp = Monto::cero();      // remuneración afecta al sistema de pensiones
        $baseEssalud = Monto::cero();  // remuneración afecta a EsSalud

        // 1. Básico proporcional a los días laborados.
        $basico = Monto::redondear(
            Monto::de($t->remuneracionBasica)
                ->multipliedBy($e->diasLaborados)
                ->dividedBy(self::DIAS_MES, 10, RoundingMode::HalfUp)
        );
        $ingresos[self::BASICO] = $basico;

        // 2. Asignación familiar: porcentaje de la RMV (no se prorratea; en el régimen agrario sí, por días).
        $asignacion = Monto::cero();
        if ($t->asignacionFamiliar) {
            $asignacion = Monto::de($p->rmv)->multipliedBy($p->porcentajeAsignacionFamiliar);
            if ($agrario !== null) {
                $asignacion = $asignacion->multipliedBy($diasComputables)->dividedBy(self::DIAS_MES, 10, RoundingMode::HalfUp);
            }
            $asignacion = Monto::redondear($asignacion);
            $ingresos[self::ASIGNACION_FAMILIAR] = $asignacion;
        }

        // 3. Horas extra sobre el valor hora de la remuneración ordinaria (agrario: sobre la RB, art. 3.j de la Ley 31110).
        $valorHora = Monto::de($t->remuneracionBasica)->plus($agrario === null ? $asignacion : Monto::cero())
            ->dividedBy(self::DIAS_MES * self::HORAS_DIA, 10, RoundingMode::HalfUp);

        $he25 = Monto::de($e->horasExtra25);
        if ($he25->isPositive()) {
            $ingresos[self::HORA_EXTRA_25] = Monto::redondear(
                $valorHora->multipliedBy(BigDecimal::one()->plus($p->sobretasaHoraExtra25))->multipliedBy($he25)
            );
        }
        $he35 = Monto::de($e->horasExtra35);
        if ($he35->isPositive()) {
            $ingresos[self::HORA_EXTRA_35] = Monto::redondear(
                $valorHora->multipliedBy(BigDecimal::one()->plus($p->sobretasaHoraExtra35))->multipliedBy($he35)
            );
        }

        foreach ($ingresos as $monto) {
            $baseAfp = $baseAfp->plus($monto);
            $baseEssalud = $baseEssalud->plus($monto);
        }

        // 4. Ingresos adicionales del mes con su afectación.
        foreach ($e->ingresosAdicionales as $codigo => $ingreso) {
            $monto = Monto::redondear(Monto::de($ingreso->monto));
            $ingresos[$codigo] = $monto;
            if ($ingreso->afectoPension) {
                $baseAfp = $baseAfp->plus($monto);
            }
            if ($ingreso->afectoEssalud) {
                $baseEssalud = $baseEssalud->plus($monto);
            }
        }

        // 4b. Régimen agrario: gratificación, bonificación extraordinaria y CTS prorrateadas (inafectas) y BETA (no remunerativa).
        if ($agrario !== null) {
            if ($t->prorrateoAgrario) {
                $base = $basico->plus(isset($ingresos[self::REMUNERACION_VACACIONAL]) ? $ingresos[self::REMUNERACION_VACACIONAL] : Monto::cero());
                $gratificacion = Monto::redondear($base->multipliedBy($agrario->porcentajeGratificacion));
                $this->sumarIngreso($ingresos, self::GRATIFICACION_AGRARIA, $gratificacion);
                $this->sumarIngreso($ingresos, self::BONIFICACION_AGRARIA, Monto::redondear($gratificacion->multipliedBy($agrario->tasaBonificacion)));
                $this->sumarIngreso($ingresos, self::CTS_AGRARIA, Monto::redondear($base->multipliedBy($agrario->porcentajeCts)));
            }
            $this->sumarIngreso($ingresos, self::BETA, Monto::redondear(
                Monto::de($p->rmv)->multipliedBy($agrario->porcentajeBeta)->multipliedBy($diasComputables)->dividedBy(self::DIAS_MES, 10, RoundingMode::HalfUp)
            ));
        }

        // 5. Aportes del trabajador al sistema de pensiones.
        $aportesTrabajador = [];
        if ($t->sistemaPension === SistemaPension::Afp) {
            if ($t->tasaAfp === null) {
                throw new \InvalidArgumentException('El trabajador está en AFP pero no hay tasas AFP para el periodo.');
            }
            $tasa = $t->tasaAfp;
            $basePrima = BigDecimal::min($baseAfp, Monto::de($tasa->remuneracionMaximaAsegurable));
            $aportesTrabajador[self::AFP_COMISION] = Monto::redondear($baseAfp->multipliedBy($tasa->comision));
            $aportesTrabajador[self::AFP_PRIMA] = Monto::redondear($basePrima->multipliedBy($tasa->primaSeguro));
            $aportesTrabajador[self::AFP_APORTE] = Monto::redondear($baseAfp->multipliedBy($tasa->aporteObligatorio));
        } elseif ($t->sistemaPension === SistemaPension::Onp) {
            $aportesTrabajador[self::ONP] = Monto::redondear($baseAfp->multipliedBy($p->tasaOnp));
        }

        // 6. Descuentos manuales (adelantos, préstamos, etc.).
        $descuentos = [];
        foreach ($e->descuentos as $codigo => $monto) {
            $descuentos[$codigo] = Monto::redondear(Monto::de($monto));
        }

        // 7. Aportes del empleador: EsSalud (base mínima = RMV) o SIS (monto fijo).
        $aportesEmpleador = [];
        if ($t->seguroSalud === SeguroSalud::Sis) {
            $aportesEmpleador[self::SIS] = Monto::redondear(Monto::de($p->aporteSis));
        } elseif ($agrario !== null) {
            // Agrario: tasa según el tamaño del empleador y sin la base mínima de la RMV (tabla 22, código 0804).
            $aportesEmpleador[self::ESSALUD] = Monto::redondear($baseEssalud->multipliedBy($agrario->tasaEssalud));
        } else {
            $base = BigDecimal::max($baseEssalud, Monto::de($p->rmv));
            $aportesEmpleador[self::ESSALUD] = Monto::redondear($base->multipliedBy($p->tasaEssalud));
        }

        $totalIngresos = $this->sumar($ingresos);
        $totalDescuentos = $this->sumar($aportesTrabajador)->plus($this->sumar($descuentos));
        $totalEmpleador = $this->sumar($aportesEmpleador);

        return new ResultadoPlanilla(
            ingresos: $this->aTexto($ingresos),
            aportesTrabajador: $this->aTexto($aportesTrabajador),
            descuentos: $this->aTexto($descuentos),
            aportesEmpleador: $this->aTexto($aportesEmpleador),
            totalIngresos: Monto::texto($totalIngresos),
            totalDescuentos: Monto::texto($totalDescuentos),
            totalAportesEmpleador: Monto::texto($totalEmpleador),
            netoPagar: Monto::texto($totalIngresos->minus($totalDescuentos)),
        );
    }

    /** @param array<string, BigDecimal> $ingresos */
    private function sumarIngreso(array &$ingresos, string $codigo, BigDecimal $monto): void
    {
        $ingresos[$codigo] = isset($ingresos[$codigo]) ? $ingresos[$codigo]->plus($monto) : $monto;
    }

    /** @param array<string, BigDecimal> $montos */
    private function sumar(array $montos): BigDecimal
    {
        return array_reduce($montos, fn (BigDecimal $acc, BigDecimal $m) => $acc->plus($m), Monto::cero());
    }

    /**
     * @param  array<string, BigDecimal>  $montos
     * @return array<string, string>
     */
    private function aTexto(array $montos): array
    {
        return array_map(fn (BigDecimal $m) => Monto::texto($m), $montos);
    }
}
