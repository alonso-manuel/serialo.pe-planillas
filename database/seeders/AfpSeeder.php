<?php

namespace Database\Seeders;

use App\Models\Afp;
use Illuminate\Database\Seeder;

class AfpSeeder extends Seeder
{
    public function run(): void
    {
        // codigo_sunat: régimen pensionario de la tabla 11 de SUNAT (Excel oficial de tablas paramétricas, 09/11/2026).
        foreach ([
            ['HABITAT', 'AFP Habitat', 'HA', '25'],
            ['INTEGRA', 'AFP Integra', 'IN', '21'],
            ['PRIMA', 'Prima AFP', 'RI', '24'],
            ['PROFUTURO', 'Profuturo AFP', 'PR', '23'],
        ] as [$codigo, $nombre, $afpnet, $sunat]) {
            // codigo_afpnet: columna «AFP» del archivo de planilla de AFPnet.
            Afp::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'codigo_afpnet' => $afpnet, 'codigo_sunat' => $sunat, 'activo' => true]);
        }
    }
}
