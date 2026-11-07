<?php

namespace Database\Seeders;

use App\Models\Auditoria;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Catálogos y demo no son cambios de usuarios: no se auditan.
        Auditoria::sinRegistrar(fn () => $this->sembrar());
    }

    private function sembrar(): void
    {
        // Catálogos legales (siempre)
        $this->call([
            ParametroSeeder::class,
            AfpSeeder::class,
            TasaAfpSeeder::class,
            ConceptoSeeder::class,
        ]);

        // Datos ficticios solo fuera de producción
        if (! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
