<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Etiqueta «Beta» visible mientras el sistema está en beta pública (PLANILLAS_BETA); se apaga sin tocar código. */
class EtiquetaBetaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function por_defecto_el_sistema_se_muestra_como_beta(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('beta', true));
        $this->get(route('legal.terminos'))->assertInertia(fn (Assert $p) => $p->where('beta', true));
    }

    #[Test]
    public function se_apaga_con_la_configuracion(): void
    {
        config(['planillas.beta' => false]);

        $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('beta', false));
    }
}
