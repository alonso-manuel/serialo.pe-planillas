<?php

namespace Database\Seeders;

use App\Enums\EstadoPeriodo;
use App\Enums\RegimenLaboral;
use App\Enums\RolUsuario;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Models\Afp;
use App\Models\Empresa;
use App\Models\Periodo;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos 100 % FICTICIOS para demos. Nunca usar datos reales de clientes aquí.
 * Usuario: demo@serialo.pe / demo1234 (cambiar en cualquier instalación pública).
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(['email' => 'demo@serialo.pe'], [
            'name' => 'Usuario Demo',
            'password' => Hash::make('demo1234'),
            'email_verified_at' => now(),
            'rol' => RolUsuario::Admin,
            'activo' => true,
        ]);

        $empresas = [
            ['20000000001', 'Comercial Los Andes S.A.C.', RegimenLaboral::Microempresa, SeguroSalud::Sis],
            ['20000000010', 'Servicios Generales Huallaga E.I.R.L.', RegimenLaboral::PequenaEmpresa, SeguroSalud::Essalud],
            ['20000000028', 'Distribuidora Pacífico S.A.', RegimenLaboral::General, SeguroSalud::Essalud],
        ];

        $trabajadores = [
            ['Quispe', 'Mamani', 'Rosa Elena', '1230.00', true, SistemaPension::Afp],
            ['Torres', 'Huamán', 'Luis Alberto', '1500.00', false, SistemaPension::Onp],
            ['Flores', 'Ramos', 'Carmen Julia', '1800.00', true, SistemaPension::Afp],
            ['García', 'Salazar', 'Jorge Iván', '1130.00', false, SistemaPension::Onp],
            ['Rojas', 'Paredes', 'Milagros', '2500.00', true, SistemaPension::Afp],
            ['Chávez', 'Vega', 'Pedro Pablo', '1350.00', false, SistemaPension::Afp],
            ['Mendoza', 'Cruz', 'Ana Lucía', '3200.00', true, SistemaPension::Onp],
            ['Díaz', 'Castillo', 'Raúl', '1130.00', false, SistemaPension::Afp],
            ['Vargas', 'León', 'Sofía', '2100.00', false, SistemaPension::Afp],
            ['Ríos', 'Medina', 'Carlos Enrique', '4500.00', true, SistemaPension::Onp],
        ];

        $afps = Afp::pluck('id')->all();
        $dni = 70000001;

        foreach ($empresas as $i => [$ruc, $razon, $regimen, $seguro]) {
            $empresa = Empresa::updateOrCreate(['ruc' => $ruc], [
                'razon_social' => $razon,
                'regimen_laboral' => $regimen,
                'seguro_salud' => $seguro,
                'direccion' => 'Av. Ficticia 123',
                'activo' => true,
            ]);
            $empresa->establecimientos()->firstOrCreate(['codigo' => '0000'], ['nombre' => 'Principal']);
            $admin->empresas()->syncWithoutDetaching([$empresa->id]);

            $periodo = Periodo::firstOrCreate(
                ['empresa_id' => $empresa->id, 'anio' => now()->year, 'mes' => now()->month],
                ['estado' => EstadoPeriodo::Abierto],
            );

            // Reparte los 10 trabajadores: 3 + 3 + 4
            foreach (array_slice($trabajadores, $i * 3, $i === 2 ? 4 : 3) as $k => [$ap, $am, $nom, $sueldo, $asig, $sistema]) {
                $esAfp = $sistema === SistemaPension::Afp;
                $t = $empresa->trabajadores()->updateOrCreate(
                    ['tipo_documento' => '01', 'numero_documento' => (string) $dni++],
                    [
                        'apellido_paterno' => $ap, 'apellido_materno' => $am, 'nombres' => $nom,
                        'fecha_ingreso' => now()->subYears(1 + $k)->startOfMonth(),
                        'remuneracion_basica' => $sueldo,
                        'asignacion_familiar' => $asig,
                        'sistema_pension' => $sistema,
                        'afp_id' => $esAfp ? $afps[$k % max(count($afps), 1)] ?? null : null,
                        'tipo_comision_afp' => $esAfp ? 'flujo' : null,
                        'seguro_salud' => $seguro,
                    ],
                );
                $periodo->lineas()->firstOrCreate(['trabajador_id' => $t->id], ['dias_laborados' => 30]);
            }
        }
    }
}
