<?php

namespace App\Console\Commands;

use App\Enums\RolUsuario;
use App\Models\Central\Estudio;
use App\Models\User;
use App\Support\ModoSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * Modo SaaS: crea un estudio (cliente o demo) con su base limpia —solo catálogos legales— y su
 * primer administrador. Lo usará también el panel central.
 */
class CrearEstudio extends Command
{
    protected $signature = 'estudio:crear
        {subdominio : p. ej. garcia → garcia.serialo.pe}
        {nombre : nombre del estudio o empresa}
        {correo : correo del primer administrador}
        {--admin=Administrador : nombre del administrador}
        {--clave= : contraseña inicial (si se omite, se genera una segura)}';

    protected $description = 'Crea un estudio (SaaS) con su propia base de datos y su administrador';

    /** Subdominios que no pueden ser estudios. */
    public const RESERVADOS = ['www', 'admin', 'app', 'api', 'mail', 'smtp', 'ftp', 'cpanel', 'webmail', 'soporte', 'static', 'assets', 'demo', 'blog'];

    public function handle(): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas.');

            return self::FAILURE;
        }

        $datos = [
            'subdominio' => Str::lower(trim((string) $this->argument('subdominio'))),
            'nombre' => trim((string) $this->argument('nombre')),
            'correo' => Str::lower(trim((string) $this->argument('correo'))),
        ];

        $validador = Validator::make($datos, [
            'subdominio' => ['required', 'regex:/^[a-z][a-z0-9-]{1,28}[a-z0-9]$/', 'not_in:'.implode(',', self::RESERVADOS)],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email'],
        ], [
            'subdominio.regex' => 'El subdominio debe tener de 3 a 30 caracteres: minúsculas, números y guiones, empezando con letra.',
            'subdominio.not_in' => 'Ese subdominio está reservado.',
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        if (Estudio::query()->whereKey($datos['subdominio'])->exists()) {
            $this->error("Ya existe un estudio con el subdominio «{$datos['subdominio']}».");

            return self::FAILURE;
        }

        $clave = (string) ($this->option('clave') ?: Str::password(16));
        $estudio = null;

        try {
            // Crea la base, corre las migraciones y carga los catálogos (TenancyServiceProvider).
            $estudio = Estudio::create(['id' => $datos['subdominio'], 'nombre' => $datos['nombre']]);
            $estudio->domains()->create(['domain' => $datos['subdominio']]);

            $estudio->run(function () use ($datos, $clave): void {
                $admin = User::query()->create([
                    'name' => (string) $this->option('admin'),
                    'email' => $datos['correo'],
                    'password' => $clave,
                    'rol' => RolUsuario::Admin,
                    'activo' => true,
                ]);
                $admin->forceFill(['email_verified_at' => now()])->save();
            });
        } catch (Throwable $e) {
            $estudio?->delete(); // también borra su base

            $this->error('No se pudo crear el estudio: '.$e->getMessage());

            return self::FAILURE;
        }

        $dominio = collect((array) config('tenancy.central_domains'))->first() ?? 'localhost';

        $this->info("Estudio «{$datos['nombre']}» creado.");
        $this->table(['Dato', 'Valor'], [
            ['Dirección', "https://{$datos['subdominio']}.{$dominio}"],
            ['Usuario', $datos['correo']],
            ['Contraseña', $clave],
        ]);
        $this->warn('Entrega la contraseña por un medio seguro; el usuario puede cambiarla en su perfil.');

        return self::SUCCESS;
    }
}
