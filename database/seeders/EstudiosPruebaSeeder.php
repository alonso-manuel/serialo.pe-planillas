<?php

namespace Database\Seeders;

use App\Models\Auditoria;
use App\Models\Central\Estudio;
use App\Support\ModoSistema;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Modo SaaS: cuatro estudios de PRUEBA con datos ficticios distintos para probar el multitenant
 * (cada uno con dos empresas, sus trabajadores y un año de planillas). Contraseña común: CLAVE.
 *
 *   php artisan db:seed --class=EstudiosPruebaSeeder
 *
 * Si un estudio ya existe, se deja como está.
 */
class EstudiosPruebaSeeder extends Seeder
{
    public const CLAVE = 'Demo-Serialo-2026!';

    /** subdominio => [nombre, correo del administrador, nombre del administrador, empresas del catálogo] */
    public const ESTUDIOS = [
        'garcia' => ['Estudio Contable García (prueba)', 'admin@garcia.test', 'Ana García', ['andes', 'misti']],
        'lopez' => ['Contadores López & Asociados (prueba)', 'admin@lopez.test', 'Jorge López', ['huallaga', 'chira']],
        'quispe' => ['Asesoría Contable Quispe (prueba)', 'admin@quispe.test', 'Rosa Quispe', ['pacifico', 'espiga']],
        'torres' => ['Empresa Torres (prueba)', 'admin@torres.test', 'Luis Torres', ['ucayali', 'sonrisa']],
    ];

    public function run(): void
    {
        if (! ModoSistema::saas()) {
            throw new RuntimeException('EstudiosPruebaSeeder solo funciona con PLANILLAS_MODO=saas.');
        }

        foreach (self::ESTUDIOS as $subdominio => [$nombre, $correo, $admin, $empresas]) {
            if (Estudio::query()->whereKey($subdominio)->exists()) {
                $this->command?->warn("«{$subdominio}» ya existe: se deja como está.");

                continue;
            }

            $codigo = Artisan::call('estudio:crear', [
                'subdominio' => $subdominio, 'nombre' => $nombre, 'correo' => $correo,
                '--admin' => $admin, '--clave' => self::CLAVE,
            ]);
            if ($codigo !== 0) {
                throw new RuntimeException("No se pudo crear «{$subdominio}»: ".Artisan::output());
            }

            Estudio::query()->findOrFail($subdominio)->run(function () use ($empresas): void {
                Auditoria::sinRegistrar(function () use ($empresas): void {
                    $datos = app(EstudioDemoSeeder::class);
                    $datos->empresasElegidas = $empresas;
                    $datos->setContainer(app())->__invoke();

                    app(HistorialDemoSeeder::class)->setContainer(app())->__invoke();
                });
            });

            $this->command?->info("✓ {$subdominio}: {$nombre} ({$correo})");
        }
    }
}
