<?php

// Caso del video comparativo «Excel vs. Serialo» (comparativo.js): una empresa FICTICIA del régimen general con
// 10 trabajadores y la planilla de noviembre 2026 abierta, sin novedades (se registran en el video).
// Uso, sobre la BD recién sembrada:  php tools/videos/comparativo-preparar.php
// Los mismos datos están en comparativo-caso.json (lo usa la recreación en Excel).

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Services\Planilla\PeriodosPlanilla;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$caso = json_decode(file_get_contents(__DIR__.'/comparativo-caso.json'), true);

$empresa = Empresa::updateOrCreate(['ruc' => $caso['empresa']['ruc']], [
    'razon_social' => $caso['empresa']['razon_social'],
    'regimen_laboral' => RegimenLaboral::General,
    'seguro_salud' => SeguroSalud::Essalud,
    'direccion' => $caso['empresa']['direccion'],
    'activo' => true,
]);
$empresa->establecimientos()->firstOrCreate(['codigo' => '0000'], ['nombre' => 'Principal']);

foreach ($caso['trabajadores'] as $k => $t) {
    $esAfp = $t['pension'] !== 'ONP';
    $empresa->trabajadores()->updateOrCreate(
        ['tipo_documento' => TipoDocumento::Dni, 'numero_documento' => $t['dni']],
        [
            'apellido_paterno' => $t['apellido_paterno'], 'apellido_materno' => $t['apellido_materno'],
            'nombres' => $t['nombres'], 'cargo' => $t['cargo'],
            'fecha_nacimiento' => sprintf('%d-03-15', 1985 + $k),
            'sexo' => $t['sexo'],
            'fecha_ingreso' => $t['ingreso'],
            'tipo_contrato' => TipoContrato::Indeterminado,
            'remuneracion_basica' => $t['sueldo'],
            'asignacion_familiar' => $t['asignacion_familiar'],
            'sistema_pension' => $esAfp ? SistemaPension::Afp : SistemaPension::Onp,
            'afp_id' => $esAfp ? Afp::query()->where('nombre', 'like', '%'.$t['pension'].'%')->value('id') : null,
            'cuspp' => $esAfp ? substr($t['dni'].'EJEMPLO', 0, 12) : null,
            'tipo_comision_afp' => $esAfp ? TipoComisionAfp::Mixta : null,
            'seguro_salud' => SeguroSalud::Essalud,
        ],
    );
}

if (! Periodo::query()->where('empresa_id', $empresa->id)->exists()) {
    app(PeriodosPlanilla::class)->abrir($empresa, 2026, 11);
}

echo "Listo: {$empresa->razon_social} con ".$empresa->trabajadores()->count()." trabajadores y noviembre 2026 abierto.\n";
