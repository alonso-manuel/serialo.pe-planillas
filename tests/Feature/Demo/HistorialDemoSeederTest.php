<?php

namespace Tests\Feature\Demo;

use App\Enums\EstadoPeriodo;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use Database\Seeders\CatalogosSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\HistorialDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** Historia ficticia para los videos de demo: un año de planillas cerradas sobre el DemoSeeder. */
class HistorialDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function deja_un_anio_de_planillas_cerradas_con_gratificacion_de_julio(): void
    {
        $this->travelTo('2026-11-09 10:00');
        $this->seed([CatalogosSeeder::class, DemoSeeder::class]);
        $auditorias = Auditoria::query()->count();

        $this->seed(HistorialDemoSeeder::class);
        $this->seed(HistorialDemoSeeder::class); // se puede repetir sin duplicar

        $general = Empresa::query()->where('ruc', '20000000028')->sole();
        $meses = Periodo::query()->where('empresa_id', $general->id)
            ->orderBy('anio')->orderBy('mes')->get();

        // Dic 2025 – oct 2026 cerrados y noviembre abierto (del DemoSeeder).
        $this->assertCount(12, $meses);
        $this->assertSame([2025, 12], [$meses->first()?->anio, $meses->first()?->mes]);
        $this->assertSame(11, $meses->where('estado', EstadoPeriodo::Cerrado)->count());

        $julio = $meses->firstWhere('mes', 7);
        $gratificacion = DB::table('planilla_conceptos')
            ->join('planilla_lineas', 'planilla_lineas.id', '=', 'planilla_conceptos.planilla_linea_id')
            ->join('conceptos', 'conceptos.id', '=', 'planilla_conceptos.concepto_id')
            ->where('planilla_lineas.periodo_id', $julio?->id)
            ->where('conceptos.codigo_plame', '0406')
            ->sum('planilla_conceptos.monto_pagado');
        $this->assertGreaterThan(0, (float) $gratificacion);

        // Correo y cuenta CTS para todos menos uno; la historia no llena la auditoría.
        $this->assertSame(5, Trabajador::query()->where('empresa_id', $general->id)->whereNotNull('cuenta_cts')->count());
        $this->assertSame($auditorias, Auditoria::query()->count());
    }
}
