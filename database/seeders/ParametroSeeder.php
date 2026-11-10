<?php

namespace Database\Seeders;

use App\Enums\ClaveParametro as C;
use App\Models\Parametro;
use Illuminate\Database\Seeder;

/**
 * Parámetros legales con vigencia. NUNCA se editan valores pasados: un cambio legal
 * es una fila nueva con su "vigente_desde". Verificar cada valor con la norma antes de vender.
 */
class ParametroSeeder extends Seeder
{
    /**
     * Parámetros de la gratificación (semana 4). También los inserta la migración
     * `add_parametros_gratificacion` en instalaciones existentes.
     *
     * @return list<array{0: C, 1: string, 2: string, 3: string}>
     */
    public static function gratificacion(): array
    {
        return [
            [C::TasaBonificacionExtraordinaria, '0.09', '2015-07-01', 'Ley 30334'],
            [C::FactorGratificacionPequena, '0.50', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE) — VERIFICAR'],
            [C::FactorGratificacionMicro, '0.00', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE) — VERIFICAR'],
            [C::FactorCtsPequena, '0.50', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE) — VERIFICAR'],
            [C::FactorCtsMicro, '0.00', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE) — VERIFICAR'],
            [C::DiasVacacionesGeneral, '30', '1991-11-01', 'D.Leg. 713, art. 10'],
            [C::DiasVacacionesPequena, '15', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE)'],
            [C::DiasVacacionesMicro, '15', '2013-07-01', 'Ley 30056 (D.S. 013-2013-PRODUCE)'],
        ];
    }

    /**
     * Renta de quinta categoría (v1.1). También los inserta la migración `add_quinta_categoria`.
     *
     * @return list<array{0: C, 1: string, 2: string, 3: string}>
     */
    public static function quinta(): array
    {
        return [
            [C::QuintaDeduccionUit, '7', '2015-01-01', 'LIR, art. 46'],
            [C::QuintaTramo1Uit, '5', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTramo2Uit, '20', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTramo3Uit, '35', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTramo4Uit, '45', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTasa1, '0.08', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTasa2, '0.14', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTasa3, '0.17', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTasa4, '0.20', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
            [C::QuintaTasa5, '0.30', '2015-01-01', 'Ley 30296 (LIR, art. 53)'],
        ];
    }

    public function run(): void
    {
        $filas = [
            [C::Rmv, '1130.00', '2025-01-01', 'D.S. 006-2024-TR'],
            [C::Uit, '5350.00', '2025-01-01', 'D.S. 260-2024-EF — VERIFICAR UIT 2026'],
            [C::TasaEssalud, '0.09', '2000-01-01', 'Ley 26790'],
            [C::AporteSis, '15.00', '2013-01-01', 'SIS microempresa — VERIFICAR monto vigente'],
            [C::TasaOnp, '0.13', '2000-01-01', 'D.L. 19990'],
            [C::PorcentajeAsignacionFamiliar, '0.10', '1988-01-01', 'Ley 25129'],
            [C::SobretasaHoraExtra25, '0.25', '2002-01-01', 'D.S. 007-2002-TR'],
            [C::SobretasaHoraExtra35, '0.35', '2002-01-01', 'D.S. 007-2002-TR'],
            ...self::gratificacion(),
            ...self::quinta(),
        ];

        foreach ($filas as [$clave, $valor, $desde, $base]) {
            Parametro::updateOrCreate(
                ['clave' => $clave->value, 'vigente_desde' => $desde],
                ['valor' => $valor, 'base_legal' => $base],
            );
        }
    }
}
