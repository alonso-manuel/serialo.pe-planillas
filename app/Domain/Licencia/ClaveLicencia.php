<?php

namespace App\Domain\Licencia;

use DateTimeImmutable;

/**
 * Formato de la clave: `SERIALO-<datos>.<firma>`, ambos en base64url.
 * `datos` es el JSON de la licencia y `firma` su firma Ed25519 (sodium).
 *
 * El sistema solo tiene la clave pública: puede verificar, no emitir. La privada vive
 * en el generador de licencias de Serialo.pe, fuera de este repositorio.
 */
final class ClaveLicencia
{
    public const PREFIJO = 'SERIALO-';

    private const CAMPOS_TEXTO = ['cliente', 'plan', 'dominio'];

    private const CAMPOS_FECHA = ['emitida', 'actualizaciones_hasta'];

    /**
     * Firma los datos (lo usan los tests y el generador externo).
     *
     * @param  array<string, mixed>  $datos
     */
    public static function firmar(array $datos, string $clavePrivadaBase64): string
    {
        $json = (string) json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $privada = base64_decode($clavePrivadaBase64, true);

        if ($privada === false || strlen($privada) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \InvalidArgumentException('La clave privada no es una clave Ed25519 en base64.');
        }

        $firma = sodium_crypto_sign_detached($json, $privada);

        return self::PREFIJO.self::b64($json).'.'.self::b64($firma);
    }

    /** @throws ClaveLicenciaInvalida */
    public static function leer(string $clave, string $clavePublicaBase64): Licencia
    {
        $clave = (string) preg_replace('/\s+/', '', $clave);

        if (! str_starts_with($clave, self::PREFIJO) || substr_count($clave, '.') !== 1) {
            throw new ClaveLicenciaInvalida('El texto no es una clave de licencia de Serialo Planillas.');
        }

        [$datos, $firma] = explode('.', substr($clave, strlen(self::PREFIJO)));
        $json = self::deB64($datos);
        $firmaBinaria = self::deB64($firma);
        $publica = (string) base64_decode($clavePublicaBase64, true);

        if ($json === null || $firmaBinaria === null || strlen($firmaBinaria) !== SODIUM_CRYPTO_SIGN_BYTES
            || strlen($publica) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new ClaveLicenciaInvalida('La clave de licencia está incompleta o mal copiada.');
        }

        if (! sodium_crypto_sign_verify_detached($firmaBinaria, $json, $publica)) {
            throw new ClaveLicenciaInvalida('La firma de la clave no es válida: fue modificada o no la emitió Serialo.pe.');
        }

        return self::licencia(json_decode($json, true));
    }

    private static function licencia(mixed $datos): Licencia
    {
        $incompleta = new ClaveLicenciaInvalida('La clave de licencia tiene datos incompletos. Pide una nueva a Serialo.pe.');

        if (! is_array($datos)) {
            throw $incompleta;
        }

        foreach (self::CAMPOS_TEXTO as $campo) {
            if (! is_string($datos[$campo] ?? null) || trim($datos[$campo]) === '') {
                throw $incompleta;
            }
        }

        foreach (self::CAMPOS_FECHA as $campo) {
            $fecha = is_string($datos[$campo] ?? null) ? DateTimeImmutable::createFromFormat('!Y-m-d', $datos[$campo]) : false;
            if ($fecha === false || $fecha->format('Y-m-d') !== $datos[$campo]) {
                throw $incompleta;
            }
        }

        $limites = [];
        foreach (Recurso::cases() as $recurso) {
            $valor = $datos[$recurso->campo()] ?? null;
            if ($valor !== null && (! is_int($valor) || $valor < 1)) {
                throw $incompleta;
            }
            $limites[$recurso->value] = $valor;
        }

        return new Licencia(
            cliente: trim($datos['cliente']),
            plan: trim($datos['plan']),
            maxEmpresas: $limites['empresas'],
            maxUsuarios: $limites['usuarios'],
            maxTrabajadores: $limites['trabajadores'],
            dominio: Licencia::normalizarHost($datos['dominio']),
            emitida: $datos['emitida'],
            actualizacionesHasta: $datos['actualizaciones_hasta'],
        );
    }

    private static function b64(string $binario): string
    {
        return rtrim(strtr(base64_encode($binario), '+/', '-_'), '=');
    }

    private static function deB64(string $texto): ?string
    {
        if ($texto === '' || preg_match('/^[A-Za-z0-9_-]+$/', $texto) !== 1) {
            return null;
        }

        $binario = base64_decode(strtr($texto, '-_', '+/'), true);

        return $binario === false ? null : $binario;
    }
}
