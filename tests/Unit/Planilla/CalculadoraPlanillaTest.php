<?php

namespace Tests\Unit\Planilla;

use App\Domain\Planilla\CalculadoraPlanilla;
use App\Domain\Planilla\DatosTrabajador;
use App\Domain\Planilla\EntradaPlanilla;
use App\Domain\Planilla\IngresoAdicional;
use App\Domain\Planilla\ParametrosPeriodo;
use App\Domain\Planilla\TasaAfpPeriodo;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Cada caso nuevo validado por un contador se agrega aquí con su origen.
 * Regla: ningún cambio en el motor se publica si algún caso falla.
 */
class CalculadoraPlanillaTest extends TestCase
{
    private function parametros(): ParametrosPeriodo
    {
        // RMV 1130, EsSalud 9 %, SIS S/ 15, ONP 13 %, asig. familiar 10 % RMV, HE 25 % / 35 %
        return new ParametrosPeriodo('1130.00', '0.09', '15.00', '0.13', '0.10', '0.25', '0.35');
    }

    #[Test]
    public function caso_real_microempresa_afp_con_sis(): void
    {
        // Origen: planilla real presentada (oct. 2026), datos anonimizados. Validado contra el Excel del contador.
        $r = (new CalculadoraPlanilla)->calcular(
            new DatosTrabajador('1230.00', false, SistemaPension::Afp, SeguroSalud::Sis,
                new TasaAfpPeriodo('0.10', '0.0137', '0', '12000.00')),
            new EntradaPlanilla,
            $this->parametros(),
        );

        $this->assertSame('1230.00', $r->ingresos['0121']);
        $this->assertSame('123.00', $r->aportesTrabajador['0608']);
        $this->assertSame('16.85', $r->aportesTrabajador['0606']);
        $this->assertSame('0.00', $r->aportesTrabajador['0601']);
        $this->assertSame('139.85', $r->totalDescuentos);
        $this->assertSame('1090.15', $r->netoPagar);
        $this->assertSame('15.00', $r->aportesEmpleador['0811']);
    }

    #[Test]
    public function onp_con_asignacion_familiar_horas_extra_y_descuento(): void
    {
        // Caso de fórmula (no validado aún con planilla real).
        $r = (new CalculadoraPlanilla)->calcular(
            new DatosTrabajador('1500.00', true, SistemaPension::Onp, SeguroSalud::Essalud),
            new EntradaPlanilla(30, '2', '1', ['0909' => new IngresoAdicional('100.00', false, false)], ['0701' => '200.00']),
            $this->parametros(),
        );

        $this->assertSame('113.00', $r->ingresos['0201']);
        $this->assertSame('16.80', $r->ingresos['0105']);
        $this->assertSame('9.07', $r->ingresos['0106']);
        $this->assertSame('213.05', $r->aportesTrabajador['0607']); // 13 % de 1638.87 (sin el ingreso no afecto)
        $this->assertSame('147.50', $r->aportesEmpleador['0804']);
        $this->assertSame('1325.82', $r->netoPagar);
    }

    #[Test]
    public function essalud_usa_como_minimo_la_rmv(): void
    {
        $r = (new CalculadoraPlanilla)->calcular(
            new DatosTrabajador('1130.00', false, SistemaPension::Onp, SeguroSalud::Essalud),
            new EntradaPlanilla(diasLaborados: 15),
            $this->parametros(),
        );

        $this->assertSame('565.00', $r->ingresos['0121']);
        $this->assertSame('101.70', $r->aportesEmpleador['0804']); // 9 % de la RMV, no de 565
    }

    #[Test]
    public function la_prima_afp_respeta_la_remuneracion_maxima_asegurable(): void
    {
        $r = (new CalculadoraPlanilla)->calcular(
            new DatosTrabajador('20000.00', false, SistemaPension::Afp, SeguroSalud::Essalud,
                new TasaAfpPeriodo('0.10', '0.0137', '0.0155', '12000.00')),
            new EntradaPlanilla,
            $this->parametros(),
        );

        $this->assertSame('164.40', $r->aportesTrabajador['0606']);  // 1.37 % de 12000 (tope)
        $this->assertSame('2000.00', $r->aportesTrabajador['0608']); // 10 % de 20000
        $this->assertSame('310.00', $r->aportesTrabajador['0601']);  // 1.55 % de 20000
    }

    #[Test]
    public function falla_si_el_trabajador_afp_no_tiene_tasas(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new CalculadoraPlanilla)->calcular(
            new DatosTrabajador('1500.00', false, SistemaPension::Afp, SeguroSalud::Essalud),
            new EntradaPlanilla,
            $this->parametros(),
        );
    }
}
