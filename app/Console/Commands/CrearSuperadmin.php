<?php

namespace App\Console\Commands;

use App\Models\Central\Superadmin;
use App\Support\ModoSistema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Modo SaaS: crea el superadmin del panel central (una sola vez). Al primer ingreso el panel
 * le pide configurar el doble factor; sin él no puede administrar cuentas.
 */
class CrearSuperadmin extends Command
{
    protected $signature = 'superadmin:crear {correo} {--nombre=Superadmin} {--clave= : si se omite, se pide en pantalla}';

    protected $description = 'Crea el superadmin del panel central (SaaS)';

    public function handle(): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas.');

            return self::FAILURE;
        }

        $correo = Str::lower(trim((string) $this->argument('correo')));
        $clave = (string) ($this->option('clave') ?: $this->secret('Contraseña (12+ caracteres, con mayúsculas, minúsculas, números y símbolos)'));

        $validador = Validator::make(['correo' => $correo, 'clave' => $clave], [
            'correo' => ['required', 'email', 'unique:'.Superadmin::class.',email'],
            'clave' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Superadmin::query()->create(['name' => (string) $this->option('nombre'), 'email' => $correo, 'password' => $clave]);

        $this->info("Superadmin {$correo} creado. Ingresa en https://".config('planillas.dominio_panel').' y configura el doble factor.');

        return self::SUCCESS;
    }
}
