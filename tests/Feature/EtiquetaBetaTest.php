<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Desde la versión 1.0.0: sin etiqueta «Beta» salvo que se encienda (PLANILLAS_BETA), y la versión al pie de página. */
class EtiquetaBetaTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function por_defecto_es_la_version_1_sin_beta(): void
    {
        $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('beta', false)->where('version', config('planillas.version')));
        $this->get(route('legal.terminos'))->assertInertia(fn (Assert $p) => $p->where('beta', false));
    }

    #[Test]
    public function la_beta_se_puede_encender_con_la_configuracion(): void
    {
        config(['planillas.beta' => true]);

        $this->get(route('login'))->assertInertia(fn (Assert $p) => $p->where('beta', true));
    }
}
