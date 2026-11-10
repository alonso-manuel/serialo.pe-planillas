<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Catálogos legales que toda instalación necesita (sin datos de clientes ni demo). */
class CatalogosSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            ParametroSeeder::class,
            AfpSeeder::class,
            TasaAfpSeeder::class,
            ConceptoSeeder::class,
        ]);
    }
}
