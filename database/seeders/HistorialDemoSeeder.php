<?php

namespace Database\Seeders;

use App\Enums\RegimenLaboral;
use App\Enums\RolUsuario;
use App\Models\Auditoria;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\Trabajador;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use App\Services\Planilla\CierrePeriodo;
use App\Services\Planilla\PeriodosPlanilla;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Un año de historia FICTICIA sobre el DemoSeeder, para los videos de demo (panel, gratificaciones, CTS, reportes).
 * No se ejecuta con `migrate:fresh --seed`: `php artisan db:seed --class=HistorialDemoSeeder`.
 *
 * - Los 10 meses anteriores al que dejó cerrado el DemoSeeder: calculados y cerrados (incluye julio y diciembre).
 * - Correo y cuenta CTS (inventados) para los trabajadores de la empresa del régimen general, menos uno,
 *   para que se vean el envío de boletas y el aviso de cuenta CTS faltante.
 */
class HistorialDemoSeeder extends Seeder
{
    public function run(PeriodosPlanilla $periodos, CalcularPlanillaPeriodo $calculo, CierrePeriodo $cierre): void
    {
        // El admin del DemoSeeder o, dentro de un estudio de prueba (SaaS), su primer administrador.
        $admin = User::query()->where('email', 'demo@serialo.pe')->first()
            ?? User::query()->where('rol', RolUsuario::Admin)->orderBy('id')->firstOrFail();
        $hasta = CarbonImmutable::now()->startOfMonth()->subMonth(); // ya cerrado por el DemoSeeder

        Auditoria::sinRegistrar(function () use ($admin, $hasta, $periodos, $calculo, $cierre): void {
            foreach (Empresa::query()->where('ruc', 'like', '200000000%')->get() as $empresa) {
                for ($mes = $hasta->subMonths(10); $mes < $hasta; $mes = $mes->addMonth()) {
                    $this->mesCerrado($empresa, $mes, $admin, $periodos, $calculo, $cierre);
                }
            }
        });

        $this->datosDeContacto();
    }

    private function mesCerrado(Empresa $empresa, CarbonImmutable $mes, User $admin, PeriodosPlanilla $periodos, CalcularPlanillaPeriodo $calculo, CierrePeriodo $cierre): void
    {
        $existe = Periodo::query()->where('empresa_id', $empresa->id)
            ->where('anio', $mes->year)->where('mes', $mes->month)->exists();
        $trabajadores = $periodos->trabajadoresDelMes($empresa, $mes->year, $mes->month);

        if ($existe || $trabajadores->isEmpty()) {
            return;
        }

        // Se crea directo: abrir() solo permite el mes siguiente al último periodo.
        $periodo = Periodo::create(['empresa_id' => $empresa->id, 'anio' => $mes->year, 'mes' => $mes->month]);
        foreach ($trabajadores as $trabajador) {
            $periodo->lineas()->create(['trabajador_id' => $trabajador->id, 'dias_laborados' => 30]);
        }

        $calculo->ejecutar($periodo);
        $cierre->cerrar($periodo->refresh(), $admin);
    }

    private function datosDeContacto(): void
    {
        $empresa = Empresa::query()->where('ruc', 'like', '200000000%')
            ->where('regimen_laboral', RegimenLaboral::General)->orderBy('id')->first();
        if ($empresa === null) {
            return;
        }

        $bancos = ['BCP', 'INTERBANK', 'BBVA', 'SCOTIABANK', 'BCP'];
        $trabajadores = Trabajador::query()->where('empresa_id', $empresa->id)
            ->orderBy('numero_documento')->get();

        foreach ($trabajadores->take(5)->values() as $i => $trabajador) {
            $trabajador->updateQuietly([
                'email' => $trabajador->email ?? 'trabajador'.$trabajador->numero_documento.'@ejemplo.test',
                'entidad_cts' => $bancos[$i],
                'cuenta_cts' => '000-'.$trabajador->numero_documento.'-0-99',
            ]);
        }
    }
}
