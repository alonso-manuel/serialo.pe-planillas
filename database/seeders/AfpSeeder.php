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
            ['HABITAT', 'AFP Habitat'],
            ['INTEGRA', 'AFP Integra'],
            ['PRIMA', 'Prima AFP'],
            ['PROFUTURO', 'Profuturo AFP'],
        ] as [$codigo, $nombre]) {
            Afp::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }
    }
}
