<?php

namespace App\Services\Instalador;

use Illuminate\Encryption\Encrypter;

/**
 * Crea y actualiza el `.env` del cliente durante la instalación.
 * Parte de `.env.example` y solo reemplaza las claves indicadas.
 */
class ArchivoEnv
{
    /** Antes de instalar: asegura un `.env` de producción con APP_KEY para que funcionen sesión y CSRF. */
    public function asegurarClave(): void
    {
        if (filled(config('app.key'))) {
            return;
        }

        $clave = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher')));
        $this->escribir(['APP_KEY' => $clave, 'APP_ENV' => 'production', 'APP_DEBUG' => 'false']);

        config(['app.key' => $clave]);
        app()->forgetInstance('encrypter');
    }

    /** @param  array<string, string|null>  $valores */
    public function escribir(array $valores): void
    {
        $ruta = app()->environmentFilePath();
        $contenido = is_file($ruta) ? (string) file_get_contents($ruta) : (string) @file_get_contents(base_path('.env.example'));

        foreach ($valores as $clave => $valor) {
            $linea = $clave.'='.$this->formato((string) $valor);
            $patron = '/^'.preg_quote($clave, '/').'=.*$/m';

            $contenido = preg_match($patron, $contenido) === 1
                ? (string) preg_replace_callback($patron, fn () => $linea, $contenido)
                : rtrim($contenido, "\n").PHP_EOL.$linea.PHP_EOL;
        }

        file_put_contents($ruta, $contenido, LOCK_EX);
    }

    /** Valor seguro para dotenv: comillas simples (literal, sin interpolar `$`) salvo que lleve `'`. */
    private function formato(string $valor): string
    {
        if ($valor === '' || preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $valor) === 1) {
            return $valor;
        }

        if (! str_contains($valor, "'")) {
            return "'{$valor}'";
        }

        return '"'.addcslashes($valor, '"\\$').'"';
    }
}
