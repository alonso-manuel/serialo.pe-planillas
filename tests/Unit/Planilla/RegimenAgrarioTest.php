<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\DatosTrabajador;
use App\Domain\Planilla\EntradaPlanilla;
use App\Domain\Planilla\IngresoAdicional;
use App\Domain\Planilla\ParametrosAgrario;
use App\Domain\Planilla\ParametrosPeriodo;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Régimen laboral agrario (Ley 31110 y D.S. 005-2021-MIDAGRI). Casos de fórmula ⚠ validar con el contador:
 * - Gratificaciones (16.66 % de la RB) y CTS (9.72 % de la RB) prorrateadas en la remuneración diaria, en conceptos
 *   independientes (art. 7), salvo que el trabajador opte por las oportunidades legales (art. 8).
 * - Bonificación extraordinaria de la Ley 30334 también prorrateada (art. 15.2); gratificación inafecta.
 * - BETA (0314): 30 % de la RMV por los días computables, no remunerativa (art. 9).
 * - EsSalud con la tasa agraria según el tamaño del empleador y sin la base mínima de la RMV (tabla 22, código 0804).
 * - Asignación familiar proporcional a los días (art. 15.1).
 */
class RegimenAgrarioTest extends TestCase
{
    private function parametros(string $tasaEssalud = '0.06'): ParametrosPeriodo
    {
        return new ParametrosPeriodo('1130.00', '0.09', '15.00', '0.13', '0.10', '0.25', '0.35',
            agrario: new ParametrosAgrario(
                tasaEssalud: $tasaEssalud,
                porcentajeBeta: '0.30',
                porcentajeGratificacion: '0.1666',
                porcentajeCts: '0.0972',
                tasaBonificacion: '0.09',
            ));
    }

    private function agrario(string $basico, bool $asignacion = false, bool $prorrateo = true): DatosTrabajador
    {
        return new DatosTrabajador($basico, $asignacion, SistemaPension::Onp, SeguroSalud::Essalud,
            agrario: true, prorrateoAgrario: $prorrateo);
    }

    #[Test]
    public function mes_completo_con_beneficios_prorrateados_y_beta(): void
    {
        $r = (new CalculadoraPlanilla)->calcular($this->agrario('1500.00', asignacion: true), new EntradaPlanilla, $this->parametros());

        $this->assertSame('1500.00', $r->ingresos['0121']);
        $this->assertSame('113.00', $r->ingresos['0201']);
        $this->assertSame('249.90', $r->ingresos['0406']); // 16.66 % de la RB
        $this->assertSame('22.49', $r->ingresos['0312']);  // 9 % de la gratificación
        $this->assertSame('145.80', $r->ingresos['0904']); // 9.72 % de la RB
        $this->assertSame('339.00', $r->ingresos['0314']); // BETA: 30 % de la RMV
        $this->assertSame('209.69', $r->aportesTrabajador['0607']); // 13 % de 1,613: sin gratificación, CTS ni BETA
        $this->assertSame('96.78', $r->aportesEmpleador['0804']);   // 6 % de 1,613
        $this->assertSame('2370.19', $r->totalIngresos);
        $this->assertSame('2160.50', $r->netoPagar);
    }

    #[Test]
    public function medio_mes_sin_prorrateo_y_essalud_sin_base_minima(): void
    {
        $r = (new CalculadoraPlanilla)->calcular(
            $this->agrario('1130.00', asignacion: true, prorrateo: false),
            new EntradaPlanilla(diasLaborados: 15),
            $this->parametros(),
        );

        $this->assertSame('565.00', $r->ingresos['0121']);
        $this->assertSame('56.50', $r->ingresos['0201']);  // asignación familiar proporcional
        $this->assertSame('169.50', $r->ingresos['0314']); // BETA proporcional a 15 días
        $this->assertArrayNotHasKey('0406', $r->ingresos);
        $this->assertArrayNotHasKey('0904', $r->ingresos);
        $this->assertSame('37.29', $r->aportesEmpleador['0804']); // 6 % de 621.50, no de la RMV
    }

    #[Test]
    public function las_vacaciones_cuentan_para_el_prorrateo_y_la_beta(): void
    {
        $r = (new CalculadoraPlanilla)->calcular(
            $this->agrario('1500.00'),
            new EntradaPlanilla(diasLaborados: 20, diasVacaciones: 10, ingresosAdicionales: ['0118' => new IngresoAdicional('500.00')]),
            $this->parametros('0.09'),
        );

        $this->assertSame('1000.00', $r->ingresos['0121']);
        $this->assertSame('249.90', $r->ingresos['0406']); // sobre 1,000 + 500 de remuneración vacacional
        $this->assertSame('145.80', $r->ingresos['0904']);
        $this->assertSame('339.00', $r->ingresos['0314']);
        $this->assertSame('135.00', $r->aportesEmpleador['0804']);
    }

    #[Test]
    public function horas_extra_sobre_la_remuneracion_basica(): void
    {
        // Art. 3.j de la Ley 31110: sobretasas sobre la RB (sin la asignación familiar). 1,500 / 240 = 6.25 por hora.
        $r = (new CalculadoraPlanilla)->calcular($this->agrario('1500.00', asignacion: true), new EntradaPlanilla(30, '2'), $this->parametros());

        $this->assertSame('15.63', $r->ingresos['0105']); // 6.25 × 1.25 × 2
    }

    #[Test]
    public function sin_parametros_agrarios_no_calcula(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CalculadoraPlanilla)->calcular($this->agrario('1500.00'), new EntradaPlanilla,
            new ParametrosPeriodo('1130.00', '0.09', '15.00', '0.13', '0.10', '0.25', '0.35'));
    }
}
