<?php

namespace Tests\Concerns;

use App\Models\Central\Estudio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Modo SaaS en un test: base central en memoria (la del test) y una base SQLite real por estudio,
 * creadas con el mismo comando que en el VPS. Al terminar se borran bases y carpetas.
 */
trait ConEstudios
{
    private string $prefijoEstudios;

    /** @var list<string> */
    protected array $estudiosDelTest = [];

    protected function activarModoSaas(): void
    {
        $this->prefijoEstudios = 'test_'.Str::lower(Str::random(6)).'_';

        config([
            'planillas.modo' => 'saas',
            'tenancy.central_domains' => ['localhost'],
            'tenancy.database.central_connection' => config('database.default'),
            'tenancy.database.prefix' => $this->prefijoEstudios,
            'planillas.url_estudio' => 'http://{estudio}.localhost',
        ]);

        $this->artisan('migrate', ['--path' => [database_path('migrations/central')], '--realpath' => true])->assertSuccessful();

        $this->beforeApplicationDestroyed(function (): void {
            tenancy()->end();
            DB::purge('tenant');

            foreach (File::glob(database_path($this->prefijoEstudios.'*')) ?: [] as $archivo) {
                @unlink($archivo);
            }
            foreach ($this->estudiosDelTest as $id) {
                File::deleteDirectory(base_path('storage/estudio-'.$id));
                // Respaldo previo a eliminar y exportaciones del panel (disco local central).
                foreach (['eliminadas', 'exportaciones'] as $carpeta) {
                    foreach (File::glob(storage_path("app/private/respaldos/{$carpeta}/{$id}-*.sql.gz")) ?: [] as $archivo) {
                        @unlink($archivo);
                    }
                }
            }
        });
    }

    protected function crearEstudio(string $subdominio, string $correo, string $clave = 'Clave-Segura-2026!'): Estudio
    {
        $this->estudiosDelTest[] = $subdominio;

        $this->artisan('estudio:crear', [
            'subdominio' => $subdominio,
            'nombre' => 'Estudio '.Str::title($subdominio),
            'correo' => $correo,
            '--clave' => $clave,
        ])->assertSuccessful();

        return Estudio::query()->findOrFail($subdominio);
    }

    protected function url(Estudio $estudio, string $ruta): string
    {
        return "http://{$estudio->id}.localhost".$ruta;
    }

    /** Como en producción (un proceso por petición): sin estudio activo, sin sesión ni usuario en memoria. */
    protected function nuevaPeticion(): void
    {
        tenancy()->end();
        $this->app['session']->forgetDrivers();
        $this->app->forgetInstance('session.store');
        $this->app['auth']->forgetGuards();
    }

    protected function ingresar(Estudio $estudio, string $correo, string $clave = 'Clave-Segura-2026!'): void
    {
        $this->nuevaPeticion();

        $this->post($this->url($estudio, '/login'), ['email' => $correo, 'password' => $clave])
            ->assertSessionHasNoErrors();
    }
}
