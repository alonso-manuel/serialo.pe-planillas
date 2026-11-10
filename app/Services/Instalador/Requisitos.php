<?php

namespace App\Services\Instalador;

/** Requisitos del servidor para instalar (lo que el hosting debe tener activado). */
class Requisitos
{
    public const PHP_MINIMO = '8.3.0';

    private const EXTENSIONES = ['pdo_mysql', 'mbstring', 'openssl', 'gd', 'zip', 'dom', 'xml', 'fileinfo', 'ctype', 'tokenizer', 'curl'];

    /** @return list<array{nombre: string, ok: bool, ayuda: string}> */
    public function revisar(): array
    {
        $lista = [[
            'nombre' => 'PHP '.self::PHP_MINIMO.' o superior (tienes '.PHP_VERSION.')',
            'ok' => version_compare(PHP_VERSION, self::PHP_MINIMO, '>='),
            'ayuda' => 'En cPanel: «Seleccionar versión de PHP» o «MultiPHP Manager».',
        ]];

        foreach (self::EXTENSIONES as $ext) {
            $lista[] = [
                'nombre' => "Extensión {$ext}",
                'ok' => extension_loaded($ext),
                'ayuda' => "Actívala en cPanel → «Seleccionar versión de PHP» → Extensiones → {$ext}.",
            ];
        }

        foreach (['storage' => storage_path(), 'bootstrap/cache' => base_path('bootstrap/cache')] as $nombre => $ruta) {
            $lista[] = [
                'nombre' => "Carpeta {$nombre} con permiso de escritura",
                'ok' => is_dir($ruta) && is_writable($ruta),
                'ayuda' => "Dale permisos 755 (o 775) a la carpeta {$nombre} desde el administrador de archivos.",
            ];
        }

        $env = app()->environmentFilePath();
        $lista[] = [
            'nombre' => 'Archivo de configuración .env con permiso de escritura',
            'ok' => is_file($env) ? is_writable($env) : is_writable(dirname($env)),
            'ayuda' => 'Dale permiso de escritura a la carpeta principal del sistema (o crea un archivo .env vacío con permisos 644).',
        ];

        return $lista;
    }

    public function cumple(): bool
    {
        return collect($this->revisar())->every(fn (array $r) => $r['ok']);
    }
}
