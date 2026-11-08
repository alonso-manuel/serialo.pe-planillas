<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\DiasLaborados;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Días laborados propuestos al abrir un mes, en mes comercial de 30 días.
 * ⚠ Criterio a confirmar con el contador (práctica habitual en planillas peruanas).
 */
class DiasLaboradosTest extends TestCase
{
    /** @return array<string, array{string, ?string, int, int, int}> */
    public static function casos(): array
    {
        //                                   ingreso       cese          año   mes  días
        return [
            'todo el mes' => ['2025-06-01', null, 2026, 11, 30],
            'febrero completo también son 30' => ['2025-06-01', null, 2026, 2, 30],
            'ingresa el 16' => ['2026-11-16', null, 2026, 11, 15],
            'ingresa el 1' => ['2026-11-01', null, 2026, 11, 30],
            'ingresa el 31 de un mes de 31 días' => ['2026-12-31', null, 2026, 12, 1],
            'cesa el 10' => ['2025-06-01', '2026-11-10', 2026, 11, 10],
            'cesa el último día de febrero' => ['2025-06-01', '2026-02-28', 2026, 2, 30],
            'cesa el 31' => ['2025-06-01', '2026-12-31', 2026, 12, 30],
            'ingresa el 5 y cesa el 20' => ['2026-11-05', '2026-11-20', 2026, 11, 16],
            'cese futuro no afecta el mes' => ['2025-06-01', '2027-03-31', 2026, 11, 30],
            'ingresa después del mes' => ['2026-12-01', null, 2026, 11, 0],
            'cesó antes del mes' => ['2025-06-01', '2026-10-31', 2026, 11, 0],
        ];
    }

    #[Test]
    #[DataProvider('casos')]
    public function propone_los_dias_del_mes(string $ingreso, ?string $cese, int $anio, int $mes, int $esperado): void
    {
        $this->assertSame($esperado, DiasLaborados::proponer(
            new DateTimeImmutable($ingreso),
            $cese === null ? null : new DateTimeImmutable($cese),
            $anio,
            $mes,
        ));
    }

    #[Test]
    public function trabaja_en_el_mes_si_tiene_al_menos_un_dia(): void
    {
        $this->assertTrue(DiasLaborados::trabajaEnElMes(new DateTimeImmutable('2026-11-30'), null, 2026, 11));
        $this->assertFalse(DiasLaborados::trabajaEnElMes(new DateTimeImmutable('2026-12-01'), null, 2026, 11));
        $this->assertFalse(DiasLaborados::trabajaEnElMes(new DateTimeImmutable('2025-01-01'), new DateTimeImmutable('2026-10-31'), 2026, 11));
    }
}
