<?php

namespace App\Console\Commands;

use App\Enums\TipoEstudio;
use App\Models\Central\Estudio;
use App\Services\Central\DatosDemo;
use App\Support\ModoSistema;
use Database\Seeders\EstudioDemoSeeder;
use Illuminate\Console\Command;

/**
 * Modo SaaS: carga datos 100 % FICTICIOS en un estudio que ya existe, para dar demos a prospectos
 * (docs/ventas/guion-demo.md): empresas de varios regímenes con sus trabajadores, un año de planillas cerradas
 * y el mes actual abierto con novedades, listo para «Calcular todas las empresas». RUC 200000000xx y
 * DNI 7000xxxx inventados (Ley 29733). Se puede repetir: lo que ya existe no se duplica.
 */
class DatosDemoEstudio extends Command
{
    protected $signature = 'estudio:datos-demo
        {subdominio : estudio que recibe los datos}
        {--empresas= : claves del catálogo separadas por coma (por defecto, 7 empresas de distintos regímenes)}
        {--correo= : correo real que recibe las boletas de prueba (si se omite, los trabajadores quedan sin correo)}
        {--force : no pedir confirmación aunque el estudio sea de un cliente}';

    protected $description = 'Carga empresas, trabajadores y planillas ficticias en un estudio (SaaS) para demos';

    public function handle(DatosDemo $datos): int
    {
        if (! ModoSistema::saas()) {
            $this->error('Solo disponible con PLANILLAS_MODO=saas.');

            return self::FAILURE;
        }

        $estudio = Estudio::query()->find((string) $this->argument('subdominio'));
        if ($estudio === null) {
            $this->error('No existe el estudio «'.$this->argument('subdominio').'».');

            return self::FAILURE;
        }

        $opcion = (string) $this->option('empresas');
        $empresas = $opcion === '' ? DatosDemo::POR_DEFECTO : array_values(array_filter(array_map('trim', explode(',', $opcion))));
        $desconocidas = array_diff($empresas, EstudioDemoSeeder::CLAVES);
        if ($desconocidas !== []) {
            $this->error('Empresas desconocidas: '.implode(', ', $desconocidas).'. Disponibles: '.implode(', ', EstudioDemoSeeder::CLAVES).'.');

            return self::FAILURE;
        }

        $correo = $this->option('correo');
        if ($correo !== null && filter_var($correo, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('El correo no es válido.');

            return self::FAILURE;
        }

        if ($estudio->tipo !== TipoEstudio::Demo && ! $this->option('force')
            && ! $this->confirm("«{$estudio->nombre}» no es un estudio demo. ¿Cargar datos ficticios igualmente?")) {
            return self::FAILURE;
        }

        $datos->cargar($estudio, $empresas, $correo);

        $this->info('✓ Datos ficticios cargados en «'.$estudio->nombre.'»: '.implode(', ', $empresas).'.');
        if ($correo !== null) {
            $this->line("  Las boletas de prueba se envían a {$correo}.");
        }

        return self::SUCCESS;
    }
}
