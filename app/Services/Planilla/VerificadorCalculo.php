<?php

namespace App\Services\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\CalculadoraPlanilla;
use App\Enums\ClaveParametro;
use App\Enums\SistemaPension;
use App\Models\Afp;
use App\Models\Concepto;
use App\Models\Parametro;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use App\Models\TasaAfp;
use App\Services\Trabajadores\HistorialTrabajador;

/**
 * Revisa ANTES de calcular que existan todos los datos que necesita el motor y devuelve
 * cada problema en lenguaje claro. Si hay alguno, el periodo no se calcula (cálculo atómico).
 */
class VerificadorCalculo
{
    /** Parámetros que usa el motor en cada periodo. */
    private const PARAMETROS = [
        ClaveParametro::Rmv,
        ClaveParametro::TasaEssalud,
        ClaveParametro::AporteSis,
        ClaveParametro::TasaOnp,
        ClaveParametro::PorcentajeAsignacionFamiliar,
        ClaveParametro::SobretasaHoraExtra25,
        ClaveParametro::SobretasaHoraExtra35,
        // Quinta categoría (cada mes; la proyección usa también la gratificación)
        ClaveParametro::Uit,
        ClaveParametro::QuintaDeduccionUit,
        ClaveParametro::QuintaTramo1Uit, ClaveParametro::QuintaTramo2Uit, ClaveParametro::QuintaTramo3Uit, ClaveParametro::QuintaTramo4Uit,
        ClaveParametro::QuintaTasa1, ClaveParametro::QuintaTasa2, ClaveParametro::QuintaTasa3, ClaveParametro::QuintaTasa4, ClaveParametro::QuintaTasa5,
        ClaveParametro::TasaBonificacionExtraordinaria,
        ClaveParametro::FactorGratificacionPequena,
        ClaveParametro::FactorGratificacionMicro,
    ];

    /** Conceptos que el motor genera y que deben existir en la tabla de conceptos. */
    private const CONCEPTOS_SISTEMA = [
        CalculadoraPlanilla::BASICO, CalculadoraPlanilla::ASIGNACION_FAMILIAR,
        CalculadoraPlanilla::HORA_EXTRA_25, CalculadoraPlanilla::HORA_EXTRA_35,
        CalculadoraPlanilla::AFP_COMISION, CalculadoraPlanilla::AFP_PRIMA, CalculadoraPlanilla::AFP_APORTE,
        CalculadoraPlanilla::ONP, CalculadoraPlanilla::ESSALUD, CalculadoraPlanilla::SIS,
        CalcularPlanillaPeriodo::REMUNERACION_VACACIONAL, RetencionQuinta::CODIGO,
    ];

    /** Además, en julio y diciembre (gratificación). */
    private const PARAMETROS_GRATIFICACION = [
        ClaveParametro::TasaBonificacionExtraordinaria,
        ClaveParametro::FactorGratificacionPequena,
        ClaveParametro::FactorGratificacionMicro,
    ];

    private const CONCEPTOS_GRATIFICACION = [CalculadoraGratificacion::GRATIFICACION, CalculadoraGratificacion::BONIFICACION];

    public function __construct(private readonly HistorialTrabajador $historial) {}

    /** @return list<string> */
    public function problemas(Periodo $periodo): array
    {
        $mes = sprintf('%02d/%d', $periodo->mes, $periodo->anio);
        $fecha = $periodo->ultimoDia();
        $problemas = [];

        $lineas = $periodo->lineas()->with(['trabajador.historial', 'novedades'])->get();
        if ($lineas->isEmpty()) {
            return ['No hay trabajadores en este periodo.'];
        }

        $gratificacion = in_array($periodo->mes, [7, 12], true);

        foreach ([...self::PARAMETROS, ...($gratificacion ? self::PARAMETROS_GRATIFICACION : [])] as $clave) {
            try {
                Parametro::vigente($clave, $fecha->toDateString());
            } catch (\RuntimeException) {
                $problemas[] = "Falta el parámetro «{$clave->etiqueta()}» vigente en {$mes}.";
            }
        }

        $requeridos = [...self::CONCEPTOS_SISTEMA, ...($gratificacion ? self::CONCEPTOS_GRATIFICACION : [])];
        $existentes = Concepto::query()->whereIn('codigo_plame', $requeridos)->pluck('codigo_plame')->all();
        foreach (array_diff($requeridos, $existentes) as $codigo) {
            $problemas[] = "Falta el concepto de sistema {$codigo} en la tabla de conceptos.";
        }

        // AFP: cada afiliado necesita su AFP y la tasa del mes de esa AFP.
        $sinTasa = [];
        /** @var PlanillaLinea $linea */
        foreach ($lineas as $linea) {
            $vista = $this->historial->vistaAl($linea->trabajador, $fecha);

            if ($vista->sistema_pension !== SistemaPension::Afp) {
                continue;
            }

            if ($vista->afp_id === null) {
                $problemas[] = "{$linea->trabajador->nombre_completo}: es AFP pero no tiene una AFP asignada en su ficha.";

                continue;
            }

            $tieneTasa = TasaAfp::query()
                ->where(['afp_id' => $vista->afp_id, 'anio' => $periodo->anio, 'mes' => $periodo->mes])
                ->exists();
            if (! $tieneTasa) {
                $sinTasa[$vista->afp_id][] = $linea->trabajador->nombre_completo;
            }
        }

        $afps = Afp::query()->whereKey(array_keys($sinTasa))->pluck('nombre', 'id');
        foreach ($sinTasa as $afpId => $nombres) {
            $problemas[] = "Falta la tasa AFP de {$afps[$afpId]} para {$mes} (trabajadores: ".implode('; ', $nombres).').';
        }

        return $problemas;
    }
}
