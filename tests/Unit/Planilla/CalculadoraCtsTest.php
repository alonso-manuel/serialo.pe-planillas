<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraCts;
use App\Domain\Planilla\EntradaCts;
use App\Domain\Planilla\ParametrosCts;
use App\Domain\Planilla\ResultadoCts;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * CTS (D.S. 001-97-TR). Casos de fórmula: ⚠ pendientes de validar con el contador.
 * Depósito de mayo 2027 = periodo noviembre 2026 – abril 2027.
 */
class CalculadoraCtsTest extends TestCase
{
    private const MESES = ['2026-11', '2026-12', '2027-01', '2027-02', '2027-03', '2027-04'];

    /** @return array<string, array{faltas: int, licencias: int, subsidio_enfermedad: int, variables: string}> */
    private function meses(array $cambios = []): array
    {
        $meses = array_fill_keys(self::MESES, ['faltas' => 0, 'licencias' => 0, 'subsidio_enfermedad' => 0, 'variables' => '0']);
        foreach ($cambios as $mes => $datos) {
            $meses[$mes] = [...$meses[$mes], ...$datos];
        }

        return $meses;
    }

    private function mayo(string $basico = '1500.00', bool $asignacion = false, string $grati = '0', string $ingreso = '2024-01-01', ?array $meses = null): EntradaCts
    {
        return new EntradaCts(2027, 5, new DateTimeImmutable($ingreso), $basico, $asignacion, $grati, $meses ?? $this->meses());
    }

    private function calcular(EntradaCts $e, string $factor = '1'): ResultadoCts
    {
        // RMV 1130, asignación familiar 10 %
        return (new CalculadoraCts)->calcular($e, new ParametrosCts('1130.00', '0.10', $factor));
    }

    #[Test]
    public function periodos_de_cada_deposito(): void
    {
        $this->assertSame([[2026, 11], [2026, 12], [2027, 1], [2027, 2], [2027, 3], [2027, 4]], CalculadoraCts::mesesDelPeriodo(2027, 5));
        $this->assertSame([[2026, 5], [2026, 6], [2026, 7], [2026, 8], [2026, 9], [2026, 10]], CalculadoraCts::mesesDelPeriodo(2026, 11));
    }

    #[Test]
    public function periodo_completo_con_sexto_de_gratificacion(): void
    {
        $r = $this->calcular($this->mayo(grati: '1500.00'));

        $this->assertSame('250.00', $r->sextoGratificacion);
        $this->assertSame('1750.00', $r->remuneracionComputable);
        $this->assertSame(6, $r->meses);
        $this->assertSame(0, $r->dias);
        $this->assertSame('875.00', $r->monto);                 // 1750 / 2
        $this->assertSame('2026-11-01', $r->computoDesde->format('Y-m-d'));
        $this->assertSame('2027-04-30', $r->computoHasta->format('Y-m-d'));
    }

    #[Test]
    public function con_asignacion_familiar(): void
    {
        $r = $this->calcular($this->mayo(asignacion: true, grati: '1613.00'));

        $this->assertSame('113.00', $r->asignacionFamiliar);
        $this->assertSame('268.83', $r->sextoGratificacion);
        $this->assertSame('1881.83', $r->remuneracionComputable);
        $this->assertSame('940.92', $r->monto);                 // 1881.83 / 2 = 940.915
    }

    #[Test]
    public function ingreso_a_mitad_del_periodo_cuenta_meses_y_dias(): void
    {
        // Ingreso 10/01/2027: enero 21 días + febrero a abril = 111 días.
        $r = $this->calcular($this->mayo(ingreso: '2027-01-10'));

        $this->assertSame(3, $r->meses);
        $this->assertSame(21, $r->dias);
        $this->assertSame('462.50', $r->monto);                 // 1500 × 111 / 360
        $this->assertSame('2027-01-10', $r->computoDesde->format('Y-m-d'));
    }

    #[Test]
    public function faltas_y_licencias_sin_goce_se_descuentan_por_dia(): void
    {
        $r = $this->calcular($this->mayo(meses: $this->meses(['2026-12' => ['faltas' => 3, 'licencias' => 2]])));

        $this->assertSame(5, $r->diasDescontados);
        $this->assertSame(5, $r->meses);
        $this->assertSame(25, $r->dias);
        $this->assertSame('729.17', $r->monto);                 // 1500 × 175 / 360
    }

    #[Test]
    public function el_subsidio_por_enfermedad_solo_computa_60_dias(): void
    {
        $r = $this->calcular($this->mayo(meses: $this->meses([
            '2026-12' => ['subsidio_enfermedad' => 30], '2027-01' => ['subsidio_enfermedad' => 30], '2027-02' => ['subsidio_enfermedad' => 10],
        ])));

        $this->assertSame(10, $r->diasDescontados);             // 70 − 60
        $this->assertSame('708.33', $r->monto);                 // 1500 × 170 / 360
    }

    #[Test]
    public function variables_en_tres_meses_entran_al_promedio(): void
    {
        $r = $this->calcular($this->mayo(meses: $this->meses([
            '2026-11' => ['variables' => '60.00'], '2027-01' => ['variables' => '60.00'], '2027-03' => ['variables' => '60.00'],
        ])));

        $this->assertSame('30.00', $r->promedioVariables);      // 180 / 6
        $this->assertSame('765.00', $r->monto);                 // 1530 / 2
    }

    #[Test]
    public function pequena_empresa_la_mitad_y_microempresa_nada(): void
    {
        $this->assertSame('437.50', $this->calcular($this->mayo(grati: '1500.00'), '0.5')->monto);

        $micro = $this->calcular($this->mayo(grati: '1500.00'), '0');
        $this->assertSame('0.00', $micro->monto);
        $this->assertFalse($micro->corresponde());
    }

    #[Test]
    public function ingreso_despues_del_periodo_no_tiene_derecho(): void
    {
        $r = $this->calcular($this->mayo(ingreso: '2027-05-02'));

        $this->assertFalse($r->corresponde());
        $this->assertSame(0, $r->meses);
    }

    #[Test]
    public function los_meses_sin_planilla_se_asumen_completos_y_se_informan(): void
    {
        $meses = $this->meses();
        unset($meses['2026-11'], $meses['2026-12']);

        $r = $this->calcular($this->mayo(meses: $meses));

        $this->assertSame(['2026-11', '2026-12'], $r->mesesSinPlanilla);
        $this->assertSame(6, $r->meses);
    }
}
