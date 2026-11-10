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

    /**
     * Modo SaaS: el estado sale del plan de la cuenta (base central), no de una clave firmada.
     * Vencida (demo o plan mensual sin renovar) = solo lectura, sin borrar nada. Una demo vigente avisa los días que quedan.
     */
    public static function desdePlan(
        string $cuenta,
        string $plan,
        ?int $maxEmpresas,
        ?int $maxUsuarios,
        ?int $maxTrabajadores,
        ?DateTimeImmutable $venceEn,
        DateTimeImmutable $hoy,
        bool $esDemo,
    ): self {
        $licencia = new Licencia($cuenta, $plan, $maxEmpresas, $maxUsuarios, $maxTrabajadores, '', $hoy->format('Y-m-d'), '9999-12-31');
        $vence = $venceEn?->format('d/m/Y');

        if ($venceEn !== null && $venceEn->format('Y-m-d') < $hoy->format('Y-m-d')) {
            return new self(
                TipoEstadoLicencia::SinLicencia,
                ($esDemo ? "La demo venció el {$vence}" : "El plan venció el {$vence}")
                .'. El sistema queda en solo lectura: tus datos siguen intactos. Comunícate con Serialo.pe para continuar.',
                $licencia,
            );
        }

        if ($esDemo && $venceEn !== null) {
            $restantes = (int) $hoy->setTime(0, 0)->diff($venceEn->setTime(0, 0))->days + 1;

            return new self(
                TipoEstadoLicencia::Prueba,
                ($restantes === 1 ? 'Demo: hoy es el último día' : "Demo: quedan {$restantes} días")." (vence el {$vence}).",
                $licencia,
                diasPrueba: $restantes,
            );
        }

        return new self(TipoEstadoLicencia::Valida, "Plan {$plan} de {$cuenta}".($vence ? ", vigente hasta el {$vence}." : '.'), $licencia);
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
            // Prueba de una instalación única: límites de prueba. Demo SaaS: los de su plan.
            TipoEstadoLicencia::Prueba => $this->licencia !== null ? $this->licencia->limite($recurso) : ($this->limitesPrueba[$recurso->campo()] ?? null),
            TipoEstadoLicencia::Omitida => null,
            default => $this->licencia?->limite($recurso),
        };
    }
}
