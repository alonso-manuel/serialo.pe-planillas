<?php

namespace App\Console\Commands;

use App\Enums\TipoEstudio;
use App\Models\Central\Estudio;
use App\Services\Central\GestionEstudios;
use App\Support\ModoSistema;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Modo SaaS: crea un estudio (cliente o demo) con su base limpia —solo catálogos legales— y su
 * primer administrador. Lo mismo que «Nueva cuenta» en el panel central.
 */
class CrearEstudio extends Command
{
    protected $signature = 'estudio:crear
        {subdominio : p. ej. garcia → garcia.serialo.pe}
        {nombre : nombre del estudio o empresa}
        {correo : correo del primer administrador}
        {--admin=Administrador : nombre del administrador}
        {--tipo=cliente : cliente o demo}
        {--vence= : fecha de vencimiento AAAA-MM-DD (demos)}
        {--clave= : contraseña inicial (si se omite, se genera una segura)}
        {--invitar : enviar al administrador el correo para crear su contraseña}';

    protected $description = 'Crea un estudio (SaaS) con su propia base de datos y su administrador';

    public function handle(GestionEstudios $gestion): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas.');

            return self::FAILURE;
        }

        $datos = [
            'subdominio' => Str::lower(trim((string) $this->argument('subdominio'))),
            'nombre' => trim((string) $this->argument('nombre')),
            'correo' => Str::lower(trim((string) $this->argument('correo'))),
            'tipo' => (string) $this->option('tipo'),
            'vence' => $this->option('vence'),
        ];

        $validador = Validator::make($datos, [
            'subdominio' => [...GestionEstudios::reglasSubdominio(), Rule::unique(Estudio::class, 'id')],
            'nombre' => ['required', 'string', 'max:255'],
            'correo' => ['required', 'email'],
            'tipo' => [Rule::enum(TipoEstudio::class)],
            'vence' => ['nullable', 'date_format:Y-m-d'],
        ], GestionEstudios::mensajesSubdominio());

        if ($validador->fails()) {
            foreach ($validador->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $clave = $this->option('invitar') ? null : (string) ($this->option('clave') ?: Str::password(16));

        try {
            $estudio = $gestion->crear(
                subdominio: $datos['subdominio'],
                nombre: $datos['nombre'],
                correo: $datos['correo'],
                nombreAdmin: (string) $this->option('admin'),
                tipo: TipoEstudio::from($datos['tipo']),
                venceEn: is_string($datos['vence']) ? CarbonImmutable::parse($datos['vence']) : null,
                clave: $clave,
            );
        } catch (Throwable $e) {
            $this->error('No se pudo crear el estudio: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->info("Estudio «{$datos['nombre']}» creado.");
        $filas = [['Dirección', $estudio->url()], ['Usuario', $datos['correo']]];

        if ($clave !== null) {
            $this->table(['Dato', 'Valor'], [...$filas, ['Contraseña', $clave]]);
            $this->warn('Entrega la contraseña por un medio seguro; el usuario puede cambiarla en su perfil.');

            return self::SUCCESS;
        }

        $enlace = $gestion->invitar($estudio);
        $this->table(['Dato', 'Valor'], $filas);
        $enlace === null
            ? $this->info('Se envió el correo para crear la contraseña.')
            : $this->warn("El correo no está configurado: envía este enlace al administrador:\n{$enlace}");

        return self::SUCCESS;
    }
}
