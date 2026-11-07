<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
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
