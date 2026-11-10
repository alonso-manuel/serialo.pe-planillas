<?php

declare(strict_types=1);

use App\Models\Central\Dominio;
use App\Models\Central\Estudio;
use Database\Seeders\CatalogosSeeder;
use Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper;
use Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper;
use Stancl\Tenancy\TenantDatabaseManagers\MySQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\PostgreSQLDatabaseManager;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager;

/*
| Multitenant (solo con PLANILLAS_MODO=saas): una base de datos por estudio, identificada por subdominio.
| En modo «unica» (hosting del cliente) nada de esto se usa. Ver docs/multitenant.md.
*/
return [
    'tenant_model' => Estudio::class,
    'id_generator' => null, // el id es el subdominio, lo elige quien crea el estudio
    'domain_model' => Dominio::class,

    /*
    | Dominios sin estudio: la web y el panel central. Un estudio es <subdominio>.<dominio central>.
    */
    'central_domains' => array_values(array_filter(array_map('trim', explode(',', (string) env(
        'PLANILLAS_DOMINIOS_CENTRALES',
        'impulso.cfd,admin.impulso.cfd,localhost',
    ))))),

    /*
    | Qué se separa por estudio: base de datos, caché (con etiquetas: Redis en el VPS), archivos y cola.
    */
    'bootstrappers' => [
        DatabaseTenancyBootstrapper::class,
        CacheTenancyBootstrapper::class,
        FilesystemTenancyBootstrapper::class,
        QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => env('DB_CONNECTION', 'mysql'),
        'template_tenant_connection' => null, // misma conexión que la central, con otra base
        'prefix' => env('PLANILLAS_PREFIJO_BD', 'planillas_'),
        'suffix' => '',
        'managers' => [
            'sqlite' => SQLiteDatabaseManager::class,
            'mysql' => MySQLDatabaseManager::class,
            'mariadb' => MySQLDatabaseManager::class,
            'pgsql' => PostgreSQLDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'estudio',
    ],

    'filesystem' => [
        'suffix_base' => 'estudio-',
        'disks' => ['local', 'public'],
        'root_override' => [
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        'asset_helper_tenancy' => false, // los assets compilados son los mismos para todos
    ],

    'redis' => [
        'prefix_base' => 'estudio',
        'prefixed_connections' => [],
    ],

    'features' => [],

    'routes' => false,

    // La base de cada estudio usa las mismas migraciones que una instalación única.
    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations')],
        '--realpath' => true,
    ],

    // Al crear un estudio: catálogos legales (RMV, UIT, AFP, tasas, conceptos). Sin datos demo.
    'seeder_parameters' => [
        '--class' => CatalogosSeeder::class,
        '--force' => true,
    ],
];
