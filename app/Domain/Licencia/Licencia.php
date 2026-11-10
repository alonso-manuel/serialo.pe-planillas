<?php

namespace App\Domain\Licencia;

use DateTimeImmutable;

/** Datos de una licencia ya verificada. Los límites `null` son ilimitados. */
final readonly class Licencia
{
    public function __construct(
        public string $cliente,
        public string $plan,
        public ?int $maxEmpresas,
        public ?int $maxUsuarios,
        public ?int $maxTrabajadores,
        public string $dominio,
        public string $emitida,
        public string $actualizacionesHasta,
    ) {}

    public function limite(Recurso $recurso): ?int
    {
        return match ($recurso) {
            Recurso::Empresas => $this->maxEmpresas,
            Recurso::Usuarios => $this->maxUsuarios,
            Recurso::Trabajadores => $this->maxTrabajadores,
        };
    }

    /** El dominio exacto o su variante con/sin «www.». Se ignoran mayúsculas y el puerto. */
    public function permiteDominio(string $host): bool
    {
        $base = fn (string $h) => (string) preg_replace('/^www\./', '', self::normalizarHost($h));

        return $base($host) === $base($this->dominio);
    }

    /** El plan anual cubre actualizaciones hasta esa fecha (inclusive). El uso no vence nunca. */
    public function conActualizaciones(DateTimeImmutable $hoy): bool
    {
        return $hoy->format('Y-m-d') <= $this->actualizacionesHasta;
    }

    public static function normalizarHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = (string) preg_replace('/:\d+$/', '', $host);

        return rtrim($host, '.');
    }
}
