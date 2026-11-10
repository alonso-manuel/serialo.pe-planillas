<?php

namespace Database\Seeders;

use App\Enums\RegimenLaboral;
use App\Enums\RolUsuario;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoComisionAfp;
use App\Enums\TipoContrato;
use App\Enums\TipoDocumento;
use App\Models\Afp;
use App\Models\Concepto;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\User;
use App\Services\Planilla\CalcularPlanillaPeriodo;
use App\Services\Planilla\CierrePeriodo;
use App\Services\Planilla\PeriodosPlanilla;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos 100 % FICTICIOS para demos y videos. Nunca usar datos reales de clientes aquí (Ley 29733).
 * DNI 7000xxxx y RUC 200000000xx son inventados.
 *
 * - Admin: demo@serialo.pe / demo1234 · Asistente: asistente@serialo.pe / demo1234
 *   (cambiar en cualquier instalación pública).
 * - 3 empresas (micro, pequeña y general) con 5–6 trabajadores cada una.
 * - Mes anterior: calculado y cerrado. Mes actual: abierto con novedades, sin calcular.
 *
 * @phpstan-type EmpresaDemo array{ruc: string, razon_social: string, regimen: RegimenLaboral, seguro: SeguroSalud, direccion: string,
 *     trabajadores: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: bool, 6: SistemaPension, 7: TipoContrato}>}
 */
class DemoSeeder extends Seeder
{
    public function run(PeriodosPlanilla $periodos, CalcularPlanillaPeriodo $calculo, CierrePeriodo $cierre): void
    {
        [$admin, $asistente] = $this->usuarios();

        $afps = Afp::query()->orderBy('nombre')->pluck('id')->all();
        $dni = 70000001;

        foreach ($this->empresas() as $i => $datos) {
            $empresa = Empresa::updateOrCreate(['ruc' => $datos['ruc']], [
                'razon_social' => $datos['razon_social'],
                'regimen_laboral' => $datos['regimen'],
                'seguro_salud' => $datos['seguro'],
                'direccion' => $datos['direccion'],
                'activo' => true,
            ]);
            $empresa->establecimientos()->firstOrCreate(['codigo' => '0000'], ['nombre' => 'Principal']);
            if ($i < 2 && $asistente !== null) {
                $asistente->empresas()->syncWithoutDetaching([$empresa->id]);
            }

            foreach ($datos['trabajadores'] as $k => [$ap, $am, $nom, $cargo, $sueldo, $asig, $sistema, $contrato]) {
                $esAfp = $sistema === SistemaPension::Afp;
                $empresa->trabajadores()->updateOrCreate(
                    ['tipo_documento' => TipoDocumento::Dni, 'numero_documento' => (string) $dni++],
                    [
                        'apellido_paterno' => $ap, 'apellido_materno' => $am, 'nombres' => $nom,
                        'cargo' => $cargo,
                        'fecha_nacimiento' => now()->subYears(24 + 3 * $k)->startOfYear()->addDays(40 * $k),
                        'sexo' => $k % 2 === 0 ? 'F' : 'M',
                        'fecha_ingreso' => now()->subMonths(4 + 7 * $k)->startOfMonth(),
                        'tipo_contrato' => $contrato,
                        'remuneracion_basica' => $sueldo,
                        'asignacion_familiar' => $asig,
                        'sistema_pension' => $sistema,
                        'afp_id' => $esAfp ? $afps[($i + $k) % count($afps)] : null,
                        'cuspp' => $esAfp ? $dni.'DEMO' : null, // 12 caracteres, ficticio
                        'tipo_comision_afp' => $esAfp ? ($k % 3 === 0 ? TipoComisionAfp::Mixta : TipoComisionAfp::Flujo) : null,
                        'seguro_salud' => $datos['seguro'],
                    ],
                );
            }

            $this->periodos($empresa, $admin, $periodos, $calculo, $cierre);
        }
    }

    /**
     * Administrador que cierra los periodos y asistente al que se asignan las dos primeras empresas.
     *
     * @return array{0: User, 1: User|null}
     */
    protected function usuarios(): array
    {
        $admin = User::updateOrCreate(['email' => 'demo@serialo.pe'], [
            'name' => 'Usuario Demo',
            'password' => Hash::make('demo1234'),
            'email_verified_at' => now(),
            'rol' => RolUsuario::Admin,
            'activo' => true,
        ]);
        $asistente = User::updateOrCreate(['email' => 'asistente@serialo.pe'], [
            'name' => 'Lucía Asistente',
            'password' => Hash::make('demo1234'),
            'email_verified_at' => now(),
            'rol' => RolUsuario::Asistente,
            'activo' => true,
        ]);

        return [$admin, $asistente];
    }

    /** Mes anterior calculado y cerrado; mes actual abierto con algunas novedades. */
    private function periodos(Empresa $empresa, User $admin, PeriodosPlanilla $periodos, CalcularPlanillaPeriodo $calculo, CierrePeriodo $cierre): void
    {
        $anterior = now()->startOfMonth()->subMonth();

        if (Periodo::query()->where('empresa_id', $empresa->id)->exists()) {
            return; // ya sembrado
        }

        $previo = $periodos->abrir($empresa, $anterior->year, $anterior->month);
        $calculo->ejecutar($previo);
        $cierre->cerrar($previo->refresh(), $admin);

        $actual = $periodos->abrir($empresa, now()->year, now()->month);
        $lineas = $actual->lineas()->with('trabajador')->get()->sortBy(fn ($l) => $l->trabajador->nombre_completo)->values();
        $bono = Concepto::query()->where('codigo_plame', '0909')->value('id');
        $adelanto = Concepto::query()->where('codigo_plame', '0701')->value('id');

        // Novedades típicas del mes: horas extra, una falta, movilidad y un adelanto.
        $lineas->get(0)?->update(['horas_extra_25' => '6.00', 'horas_extra_35' => '2.00']);
        $lineas->get(1)?->update(['dias_no_laborados' => 1, 'dias_laborados' => 29]);
        $lineas->get(2)?->novedades()->create(['concepto_id' => $bono, 'monto' => '150.00', 'glosa' => 'Movilidad del mes']);
        $lineas->get(3)?->novedades()->create(['concepto_id' => $adelanto, 'monto' => '300.00', 'glosa' => 'Adelanto quincena']);
    }

    /**
     * @return list<EmpresaDemo>
     */
    protected function empresas(): array
    {
        $onp = SistemaPension::Onp;
        $afp = SistemaPension::Afp;
        $ind = TipoContrato::Indeterminado;
        $fijo = TipoContrato::PlazoFijo;

        return [
            [
                'ruc' => '20000000001', 'razon_social' => 'Comercial Los Andes S.A.C.',
                'regimen' => RegimenLaboral::Microempresa, 'seguro' => SeguroSalud::Sis,
                'direccion' => 'Av. Ficticia 123, Huancayo',
                'trabajadores' => [
                    ['Quispe', 'Mamani', 'Rosa Elena', 'Vendedora', '1130.00', true, $afp, $ind],
                    ['Torres', 'Huamán', 'Luis Alberto', 'Almacenero', '1200.00', false, $onp, $fijo],
                    ['Flores', 'Ramos', 'Carmen Julia', 'Cajera', '1130.00', true, $afp, $fijo],
                    ['García', 'Salazar', 'Jorge Iván', 'Repartidor', '1150.00', false, $onp, $ind],
                    ['Lazo', 'Pinto', 'Nadia', 'Asistente administrativa', '1300.00', false, $afp, $ind],
                ],
            ],
            [
                'ruc' => '20000000010', 'razon_social' => 'Servicios Generales Huallaga E.I.R.L.',
                'regimen' => RegimenLaboral::PequenaEmpresa, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Jr. Imaginario 456, Huánuco',
                'trabajadores' => [
                    ['Rojas', 'Paredes', 'Milagros', 'Supervisora', '2500.00', true, $afp, $ind],
                    ['Chávez', 'Vega', 'Pedro Pablo', 'Técnico electricista', '1800.00', false, $afp, $fijo],
                    ['Mendoza', 'Cruz', 'Ana Lucía', 'Contadora', '3200.00', true, $onp, $ind],
                    ['Díaz', 'Castillo', 'Raúl', 'Operario de limpieza', '1130.00', false, $afp, $fijo],
                    ['Salas', 'Ugarte', 'Óscar', 'Chofer', '1600.00', true, $onp, $ind],
                ],
            ],
            [
                'ruc' => '20000000028', 'razon_social' => 'Distribuidora Pacífico S.A.',
                'regimen' => RegimenLaboral::General, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Calle Inventada 789, Lima',
                'trabajadores' => [
                    ['Vargas', 'León', 'Sofía', 'Jefa de ventas', '4200.00', false, $afp, $ind],
                    ['Ríos', 'Medina', 'Carlos Enrique', 'Gerente general', '8500.00', true, $onp, $ind],
                    ['Benites', 'Soto', 'Gabriela', 'Analista de compras', '2800.00', true, $afp, $ind],
                    ['Campos', 'Arce', 'Diego', 'Asistente de almacén', '1400.00', false, $afp, $fijo],
                    ['Ñique', 'Valdez', 'Patricia', 'Recepcionista', '1350.00', false, $onp, $fijo],
                    ['Huertas', 'Loayza', 'Martín', 'Vendedor', '1700.00', true, $afp, $ind],
                ],
            ],
        ];
    }
}
