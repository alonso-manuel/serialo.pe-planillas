<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraGratificacion;
use App\Domain\Planilla\EntradaGratificacion;
use App\Domain\Planilla\ParametrosGratificacion;
use App\Domain\Planilla\ResultadoGratificacion;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Gratificación (Ley 27735) y bonificación extraordinaria (Ley 30334).
 * Casos de fórmula: ⚠ pendientes de validar con el contador. Al validar uno, anotar su origen.
 */
class CalculadoraGratificacionTest extends TestCase
{
    /** Diciembre 2026 con planilla registrada de julio a noviembre (sin faltas ni variables). */
    private function diciembre(string $basico = '1500.00', bool $asignacion = false, string $ingreso = '2024-01-01', ?array $meses = null): EntradaGratificacion
    {
        $meses ??= array_fill_keys([7, 8, 9, 10, 11], ['faltas' => 0, 'variables' => '0']);

        return new EntradaGratificacion(2026, 12, new DateTimeImmutable($ingreso), $basico, $asignacion, $meses);
    }

    private function calcular(EntradaGratificacion $e, string $factor = '1'): ResultadoGratificacion
    {
        // RMV 1130, asignación familiar 10 %, bonificación 9 %
        return (new CalculadoraGratificacion)->calcular($e, new ParametrosGratificacion('1130.00', '0.10', $factor, '0.09'));
    }

    #[Test]
    public function semestre_completo_con_asignacion_familiar(): void
    {
        $r = $this->calcular($this->diciembre('1500.00', true));

        $this->assertSame(6, $r->mesesComputables);
        $this->assertSame('1613.00', $r->remuneracionComputable); // 1500 + 113
        $this->assertSame('1613.00', $r->gratificacion);
        $this->assertSame('145.17', $r->bonificacion);            // 9 %
        $this->assertSame([], $r->mesesSinPlanilla);
    }

    #[Test]
    public function ingreso_a_mitad_de_mes_no_cuenta_ese_mes(): void
    {
        // Ingreso 10/09: cuentan octubre, noviembre y diciembre.
        $r = $this->calcular($this->diciembre(ingreso: '2026-09-10'));

        $this->assertSame(3, $r->mesesComputables);
        $this->assertSame('750.00', $r->gratificacion);
        $this->assertSame('67.50', $r->bonificacion);
    }

    #[Test]
    public function ingreso_el_dia_uno_cuenta_el_mes(): void
    {
        $r = $this->calcular($this->diciembre(ingreso: '2026-09-01'));

        $this->assertSame(4, $r->mesesComputables);
        $this->assertSame('1000.00', $r->gratificacion);
    }

    #[Test]
    public function las_faltas_descuentan_un_ciento_ochentavo_por_dia(): void
    {
        $meses = array_fill_keys([7, 8, 9, 10, 11], ['faltas' => 0, 'variables' => '0']);
        $meses[8]['faltas'] = 2;

        $r = $this->calcular($this->diciembre('1800.00', meses: $meses));

        $this->assertSame(2, $r->diasFalta);
        $this->assertSame('1780.00', $r->gratificacion);  // 1800 − 1800/180 × 2
        $this->assertSame('160.20', $r->bonificacion);
    }

    #[Test]
    public function variables_en_tres_meses_entran_al_promedio(): void
    {
        $meses = array_fill_keys([7, 8, 9, 10, 11], ['faltas' => 0, 'variables' => '0']);
        $meses[7]['variables'] = '120.00';
        $meses[8]['variables'] = '60.00';
        $meses[10]['variables'] = '90.00';

        $r = $this->calcular($this->diciembre('1500.00', meses: $meses));

        $this->assertSame(3, $r->mesesConVariables);
        $this->assertSame('45.00', $r->promedioVariables);     // 270 / 6
        $this->assertSame('1545.00', $r->remuneracionComputable);
        $this->assertSame('1545.00', $r->gratificacion);
    }

    #[Test]
    public function variables_en_solo_dos_meses_no_cuentan(): void
    {
        $meses = array_fill_keys([7, 8, 9, 10, 11], ['faltas' => 0, 'variables' => '0']);
        $meses[7]['variables'] = '120.00';
        $meses[9]['variables'] = '60.00';

        $r = $this->calcular($this->diciembre('1500.00', meses: $meses));

        $this->assertSame(2, $r->mesesConVariables);
        $this->assertSame('0.00', $r->promedioVariables);
        $this->assertSame('1500.00', $r->gratificacion);
    }

    #[Test]
    public function pequena_empresa_recibe_la_mitad(): void
    {
        $r = $this->calcular($this->diciembre('1500.00'), factor: '0.5');

        $this->assertSame('750.00', $r->gratificacion);
        $this->assertSame('67.50', $r->bonificacion);
    }

    #[Test]
    public function microempresa_no_tiene_gratificacion(): void
    {
        $r = $this->calcular($this->diciembre('1500.00'), factor: '0');

        $this->assertSame('0.00', $r->gratificacion);
        $this->assertSame('0.00', $r->bonificacion);
        $this->assertFalse($r->corresponde());
    }

    #[Test]
    public function julio_paga_enero_a_junio(): void
    {
        $meses = array_fill_keys([1, 2, 3, 4, 5, 6], ['faltas' => 0, 'variables' => '0']);
        $meses[6]['faltas'] = 1;

        $r = $this->calcular(new EntradaGratificacion(2026, 7, new DateTimeImmutable('2026-02-01'), '1800.00', false, $meses));

        $this->assertSame(5, $r->mesesComputables);           // febrero a junio
        $this->assertSame(1, $r->diasFalta);
        $this->assertSame('1490.00', $r->gratificacion);      // 1800 × 5/6 − 10
    }

    #[Test]
    public function los_meses_sin_planilla_se_asumen_completos_y_se_informan(): void
    {
        $meses = array_fill_keys([10, 11], ['faltas' => 0, 'variables' => '0']);

        $r = $this->calcular($this->diciembre('1200.00', meses: $meses));

        $this->assertSame(6, $r->mesesComputables);
        $this->assertSame([7, 8, 9], $r->mesesSinPlanilla);
        $this->assertSame('1200.00', $r->gratificacion);
    }

    #[Test]
    public function las_faltas_de_meses_no_computables_no_restan(): void
    {
        $meses = array_fill_keys([7, 8, 9, 10, 11], ['faltas' => 0, 'variables' => '0']);
        $meses[7]['faltas'] = 30;

        // Ingreso 01/12: solo cuenta diciembre; las faltas de julio no corresponden a su récord.
        $r = $this->calcular($this->diciembre('1500.00', ingreso: '2026-12-01', meses: $meses));

        $this->assertSame(1, $r->mesesComputables);
        $this->assertSame(0, $r->diasFalta);
        $this->assertSame('250.00', $r->gratificacion);       // 1500 / 6
    }

    #[Test]
    public function rechaza_meses_que_no_son_de_pago(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->calcular(new EntradaGratificacion(2026, 11, new DateTimeImmutable('2024-01-01'), '1500.00', false));
    }
}
