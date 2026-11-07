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
        ];

        foreach ($filas as [$clave, $valor, $desde, $base]) {
            Parametro::updateOrCreate(
                ['clave' => $clave->value, 'vigente_desde' => $desde],
                ['valor' => $valor, 'base_legal' => $base],
            );
        }
    }
}
