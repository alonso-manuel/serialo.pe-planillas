<?php

namespace Database\Seeders;

use App\Models\Afp;
use App\Models\TasaAfp;
use Illuminate\Database\Seeder;

/**
 * ⚠ DATOS DE EJEMPLO para desarrollo y demos.
 * Antes de producción: cargar las tasas oficiales que publica la SBS cada mes
 * (aporte, prima, comisión flujo/mixta y remuneración máxima asegurable).
 */
class TasaAfpSeeder extends Seeder
{
    public function run(): void
    {
        $comisionFlujo = ['HABITAT' => '0.0147', 'INTEGRA' => '0.0155', 'PRIMA' => '0.0160', 'PROFUTURO' => '0.0169'];
        $hoy = now();

        foreach (Afp::all() as $afp) {
            for ($i = 0; $i < 12; $i++) {
                $f = $hoy->copy()->startOfMonth()->subMonths($i);
                TasaAfp::updateOrCreate(
                    ['afp_id' => $afp->id, 'anio' => $f->year, 'mes' => $f->month],
                    [
                        'aporte_obligatorio' => '0.1000',
                        'prima_seguro' => '0.0137',
                        'comision_flujo' => $comisionFlujo[$afp->codigo] ?? '0.0155',
                        'comision_mixta' => '0.0000',
                        'remuneracion_maxima_asegurable' => '12000.00',
                    ],
                );
            }
        }
    }
}
