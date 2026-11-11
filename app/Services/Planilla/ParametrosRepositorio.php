<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\ParametrosCts;
use App\Domain\Planilla\ParametrosGratificacion;
use App\Domain\Planilla\ParametrosIndemnizacion;
use App\Domain\Planilla\ParametrosPeriodo;
use App\Domain\Planilla\ParametrosQuinta;
use App\Domain\Planilla\TasaAfpPeriodo;
use App\Enums\ClaveParametro as C;
use App\Enums\RegimenLaboral;
use App\Enums\TipoComisionAfp;
use App\Models\Empresa;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\TasaAfp;
use App\Models\Trabajador;

/** Lee de la base de datos los parámetros vigentes de un periodo y los entrega al motor como DTO. */
class ParametrosRepositorio
{
    public function delPeriodo(Periodo $periodo): ParametrosPeriodo
    {
        $fecha = $periodo->ultimoDia()->toDateString();

        return new ParametrosPeriodo(
            rmv: Parametro::vigente(C::Rmv, $fecha),
            tasaEssalud: Parametro::vigente(C::TasaEssalud, $fecha),
            aporteSis: Parametro::vigente(C::AporteSis, $fecha),
            tasaOnp: Parametro::vigente(C::TasaOnp, $fecha),
            porcentajeAsignacionFamiliar: Parametro::vigente(C::PorcentajeAsignacionFamiliar, $fecha),
            sobretasaHoraExtra25: Parametro::vigente(C::SobretasaHoraExtra25, $fecha),
            sobretasaHoraExtra35: Parametro::vigente(C::SobretasaHoraExtra35, $fecha),
        );
    }

    /** Indemnización por despido arbitrario de una empresa, vigente a la fecha de cese (Y-m-d). */
    public function indemnizacion(Empresa $empresa, string $fecha): ParametrosIndemnizacion
    {
        $v = fn (C $clave) => (int) Parametro::vigente($clave, $fecha);

        return match ($empresa->regimen_laboral) {
            RegimenLaboral::General => new ParametrosIndemnizacion($v(C::IndemnizacionDiasAnioGeneral), $v(C::IndemnizacionTopeGeneral), $v(C::IndemnizacionDiasMesPlazoFijo)),
            RegimenLaboral::PequenaEmpresa => new ParametrosIndemnizacion($v(C::IndemnizacionDiasAnioPequena), $v(C::IndemnizacionTopePequena), null),
            RegimenLaboral::Microempresa => new ParametrosIndemnizacion($v(C::IndemnizacionDiasAnioMicro), $v(C::IndemnizacionTopeMicro), null),
        };
    }

    /** Renta de quinta categoría vigente al cierre del periodo. */
    public function quinta(Periodo $periodo): ParametrosQuinta
    {
        return $this->quintaAl($periodo->ultimoDia()->toDateString());
    }

    /** Renta de quinta categoría vigente en una fecha (Y-m-d), p. ej. el 31/12 del ejercicio. */
    public function quintaAl(string $fecha): ParametrosQuinta
    {
        $v = fn (C $clave) => Parametro::vigente($clave, $fecha);

        return new ParametrosQuinta(
            uit: $v(C::Uit),
            deduccionUit: $v(C::QuintaDeduccionUit),
            tramos: [
                [$v(C::QuintaTramo1Uit), $v(C::QuintaTasa1)],
                [$v(C::QuintaTramo2Uit), $v(C::QuintaTasa2)],
                [$v(C::QuintaTramo3Uit), $v(C::QuintaTasa3)],
                [$v(C::QuintaTramo4Uit), $v(C::QuintaTasa4)],
                [null, $v(C::QuintaTasa5)],
            ],
        );
    }

    public function gratificacion(Periodo $periodo): ParametrosGratificacion
    {
        /** @var Empresa $empresa */
        $empresa = $periodo->empresa;

        return $this->gratificacionPara($empresa, $periodo->ultimoDia()->toDateString());
    }

    /** Parámetros de la gratificación de una empresa vigentes en una fecha (Y-m-d). */
    public function gratificacionPara(Empresa $empresa, string $fecha): ParametrosGratificacion
    {
        $regimen = $empresa->regimen_laboral;

        return new ParametrosGratificacion(
            rmv: Parametro::vigente(C::Rmv, $fecha),
            porcentajeAsignacionFamiliar: Parametro::vigente(C::PorcentajeAsignacionFamiliar, $fecha),
            // Régimen general: la gratificación completa (Ley 27735). REMYPE: proporción con vigencia.
            factorRegimen: match ($regimen) {
                RegimenLaboral::General => '1',
                RegimenLaboral::PequenaEmpresa => Parametro::vigente(C::FactorGratificacionPequena, $fecha),
                RegimenLaboral::Microempresa => Parametro::vigente(C::FactorGratificacionMicro, $fecha),
            },
            tasaBonificacion: Parametro::vigente(C::TasaBonificacionExtraordinaria, $fecha),
        );
    }

    /** Parámetros de la CTS de una empresa vigentes al cierre del periodo computable (Y-m-d). */
    public function cts(Empresa $empresa, string $fecha): ParametrosCts
    {
        return new ParametrosCts(
            rmv: Parametro::vigente(C::Rmv, $fecha),
            porcentajeAsignacionFamiliar: Parametro::vigente(C::PorcentajeAsignacionFamiliar, $fecha),
            // Régimen general: CTS completa (D.S. 001-97-TR). REMYPE: proporción con vigencia.
            factorRegimen: match ($empresa->regimen_laboral) {
                RegimenLaboral::General => '1',
                RegimenLaboral::PequenaEmpresa => Parametro::vigente(C::FactorCtsPequena, $fecha),
                RegimenLaboral::Microempresa => Parametro::vigente(C::FactorCtsMicro, $fecha),
            },
        );
    }

    public function tasaAfp(Trabajador $trabajador, Periodo $periodo): ?TasaAfpPeriodo
    {
        if ($trabajador->afp_id === null) {
            return null;
        }

        $tasa = TasaAfp::query()
            ->where('afp_id', $trabajador->afp_id)
            ->where('anio', $periodo->anio)
            ->where('mes', $periodo->mes)
            ->first();

        if ($tasa === null) {
            throw new \RuntimeException("Faltan las tasas AFP de {$periodo->mes}/{$periodo->anio}.");
        }

        return new TasaAfpPeriodo(
            aporteObligatorio: (string) $tasa->aporte_obligatorio,
            primaSeguro: (string) $tasa->prima_seguro,
            comision: (string) ($trabajador->tipo_comision_afp === TipoComisionAfp::Mixta ? $tasa->comision_mixta : $tasa->comision_flujo),
            remuneracionMaximaAsegurable: (string) $tasa->remuneracion_maxima_asegurable,
        );
    }
}
