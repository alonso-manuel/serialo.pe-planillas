<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\ParametrosPeriodo;
use App\Domain\Planilla\TasaAfpPeriodo;
use App\Enums\ClaveParametro as C;
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
            comision: (string) ($trabajador->tipo_comision_afp === 'mixta' ? $tasa->comision_mixta : $tasa->comision_flujo),
            remuneracionMaximaAsegurable: (string) $tasa->remuneracion_maxima_asegurable,
        );
    }
}
