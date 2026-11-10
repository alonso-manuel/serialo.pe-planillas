<?php

namespace App\Console\Commands;

use App\Support\ModoSistema;
use Illuminate\Console\Command;

/**
 * Modo SaaS, después de cada actualización: migra la base central (estudios, dominios y la cola)
 * y luego la base de cada estudio. Nunca usar `php artisan migrate` solo en el VPS:
 * crearía las tablas de planilla en la base central.
 */
class MigrarEstudios extends Command
{
    protected $signature = 'estudios:migrar';

    protected $description = 'Migra la base central y las bases de todos los estudios (SaaS)';

    public function handle(): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas. En una instalación única usa «php artisan migrate».');

            return self::FAILURE;
        }

        $this->info('Base central…');
        $central = $this->call('migrate', [
            '--path' => self::rutasCentrales(),
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($central !== self::SUCCESS) {
            return self::FAILURE;
        }

        $this->info('Bases de los estudios…');

        return $this->call('tenants:migrate');
    }

    /** @return list<string> */
    public static function rutasCentrales(): array
    {
        return [
            database_path('migrations/central'),
            database_path('migrations/0001_01_01_000002_create_jobs_table.php'),
        ];
    }
}
