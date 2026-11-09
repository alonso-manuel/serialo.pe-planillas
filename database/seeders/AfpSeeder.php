<?php

namespace Database\Seeders;

use App\Models\Afp;
use Illuminate\Database\Seeder;

class AfpSeeder extends Seeder
{
    public function run(): void
    {
        // codigo_sunat: VERIFICAR con la tabla paramétrica de T-Registro / PLAME.
        foreach ([
            ['HABITAT', 'AFP Habitat', 'HA'],
            ['INTEGRA', 'AFP Integra', 'IN'],
            ['PRIMA', 'Prima AFP', 'RI'],
            ['PROFUTURO', 'Profuturo AFP', 'PR'],
        ] as [$codigo, $nombre, $afpnet]) {
            // codigo_afpnet: columna «AFP» del archivo de planilla de AFPnet.
            Afp::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'codigo_afpnet' => $afpnet, 'activo' => true]);
        }
    }
}
