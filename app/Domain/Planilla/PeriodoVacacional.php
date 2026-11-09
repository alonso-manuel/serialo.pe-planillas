<?php

namespace App\Domain\Planilla;

use DateTimeImmutable;

/** Un año de servicios con su derecho a vacaciones, lo gozado y el saldo. */
final readonly class PeriodoVacacional
{
    public function __construct(
        public int $numero,
        public DateTimeImmutable $desde,
        public DateTimeImmutable $hasta,
        /** El año de servicios ya se completó (genera derecho). */
        public bool $cumplido,
        public int $derecho,
        public int $gozados,
        /** Negativo = adelanto de vacaciones sobre el periodo en curso. */
        public int $saldo,
        /** Fecha límite para gozar el descanso (un año después de cumplido). */
        public DateTimeImmutable $vence,
        public bool $vencido,
        public int $diasNoEfectivos,
        public bool $avisoRecord,
    ) {}
}
