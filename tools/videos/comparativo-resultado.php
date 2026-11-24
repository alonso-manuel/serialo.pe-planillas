<?php

// Exporta la planilla de noviembre de «Inversiones Ejemplo S.A.C.» ya calculada en Serialo (después de grabar
// comparativo-serialo) a videos/comparativo-resultado.json. La recreación en Excel muestra esos mismos montos:
// las dos tomas cuadran al céntimo.   Uso: php tools/videos/comparativo-resultado.php

use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\PlanillaLinea;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$caso = json_decode((string) file_get_contents(__DIR__.'/comparativo-caso.json'), true);
$empresa = Empresa::query()->where('ruc', $caso['empresa']['ruc'])->firstOrFail();
$periodo = Periodo::query()->where('empresa_id', $empresa->id)->where('anio', 2026)->where('mes', 11)->firstOrFail();

$montos = fn (PlanillaLinea $l) => DB::table('planilla_conceptos')
    ->join('conceptos', 'conceptos.id', '=', 'planilla_conceptos.concepto_id')
    ->where('planilla_linea_id', $l->id)
    ->pluck('planilla_conceptos.monto_pagado', 'conceptos.codigo_plame')
    ->map(fn ($m) => (string) $m)
    ->all();

$trabajadores = [];
foreach ($caso['trabajadores'] as $t) {
    $linea = $periodo->lineas()->whereHas('trabajador', fn ($q) => $q->where('numero_documento', $t['dni']))->with('trabajador')->firstOrFail();
    $trabajadores[] = [
        'dni' => $t['dni'],
        'nombre' => $linea->trabajador->nombre_completo,
        'pension' => $t['pension'],
        'sueldo' => $t['sueldo'],
        'dias' => $linea->dias_laborados,
        'faltas' => (int) $linea->dias_no_laborados,
        'he25' => (string) $linea->horas_extra_25,
        'he35' => (string) $linea->horas_extra_35,
        'conceptos' => $montos($linea),
        'ingresos' => (string) $linea->total_ingresos,
        'descuentos' => (string) $linea->total_descuentos,
        'aportes' => (string) $linea->total_aportes,
        'neto' => (string) $linea->neto_pagar,
    ];
}

$salida = __DIR__.'/../../videos/comparativo-resultado.json';
file_put_contents($salida, json_encode([
    'empresa' => $caso['empresa'],
    'novedadesCaso' => $caso['novedades'],
    'trabajadores' => $trabajadores,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "Listo: {$salida}\n";
