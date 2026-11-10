<?php

namespace App\Domain\Licencia;

use DateTimeImmutable;

/**
 * Situación de la licencia en este momento y en este dominio.
 *
 * - Válida: se trabaja con los límites de la clave (al vencer el plan anual solo se dejan de recibir actualizaciones).
 * - Prueba: sin clave, durante los días de prueba desde la instalación, con los límites de prueba.
 * - Sin licencia, clave inválida u otro dominio: solo lectura. Nunca se borra nada.
 */
final readonly class EstadoLicencia
{
    /** @param array<string, int|null> $limitesPrueba max_empresas, max_usuarios, max_trabajadores */
    private function __construct(
        public TipoEstadoLicencia $tipo,
        public string $mensaje,
        public ?Licencia $licencia = null,
        public ?int $diasPrueba = null,
        private array $limitesPrueba = [],
    ) {}

    /**
     * @param  array{dias: int, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null}  $prueba
     * @param  string|null  $host  null cuando no hay petición web (cola, comandos): no se revisa el dominio
     */
    public static function evaluar(
        ?string $clave,
        string $clavePublica,
        ?string $host,
        ?DateTimeImmutable $instaladoEn,
        DateTimeImmutable $hoy,
        array $prueba,
    ): self {
        if ($clave === null || trim($clave) === '') {
            return self::prueba($instaladoEn, $hoy, $prueba);
        }

        try {
            $licencia = ClaveLicencia::leer($clave, $clavePublica);
        } catch (ClaveLicenciaInvalida $e) {
            return new self(TipoEstadoLicencia::Invalida, $e->getMessage().' El sistema queda en solo lectura hasta ingresar una clave válida.');
        }

        if ($host !== null && ! $licencia->permiteDominio($host)) {
            return new self(
                TipoEstadoLicencia::OtroDominio,
                "La licencia es para {$licencia->dominio} y el sistema se está usando en ".Licencia::normalizarHost($host)
                .'. Queda en solo lectura: pide a Serialo.pe una clave para el nuevo dominio.',
                $licencia,
            );
        }

        return new self(TipoEstadoLicencia::Valida, "Licencia {$licencia->plan} de {$licencia->cliente}.", $licencia);
    }

    public static function omitida(): self
    {
        return new self(TipoEstadoLicencia::Omitida, 'Verificación de licencia desactivada (desarrollo).');
    }

    /** @param array{dias: int, max_empresas: int|null, max_usuarios: int|null, max_trabajadores: int|null} $prueba */
    private static function prueba(?DateTimeImmutable $instaladoEn, DateTimeImmutable $hoy, array $prueba): self
    {
        if ($instaladoEn === null) {
            return new self(TipoEstadoLicencia::SinLicencia, 'No hay una licencia registrada. El sistema queda en solo lectura hasta ingresarla.');
        }

        $transcurridos = (int) $instaladoEn->setTime(0, 0)->diff($hoy->setTime(0, 0))->format('%r%a');
        $restantes = $prueba['dias'] - max(0, $transcurridos);

        if ($restantes <= 0) {
            return new self(
                TipoEstadoLicencia::SinLicencia,
                "La prueba de {$prueba['dias']} días terminó. El sistema queda en solo lectura hasta ingresar una licencia: tus datos siguen intactos.",
            );
        }

        $limites = [];
        foreach (Recurso::cases() as $recurso) {
            $limites[$recurso->campo()] = $prueba[$recurso->campo()] ?? null;
        }

        return new self(
            TipoEstadoLicencia::Prueba,
            $restantes === 1 ? 'Prueba: hoy es el último día.' : "Prueba: quedan {$restantes} días.",
            diasPrueba: $restantes,
            limitesPrueba: $limites,
        );
    }

    /** Escribir está bloqueado: solo se puede consultar y descargar. */
    public function soloLectura(): bool
    {
        return in_array($this->tipo, [TipoEstadoLicencia::SinLicencia, TipoEstadoLicencia::Invalida, TipoEstadoLicencia::OtroDominio], true);
    }

    public function limite(Recurso $recurso): ?int
    {
        return match ($this->tipo) {
            TipoEstadoLicencia::Prueba => $this->limitesPrueba[$recurso->campo()] ?? null,
            TipoEstadoLicencia::Omitida => null,
            default => $this->licencia?->limite($recurso),
        };
    }
}
