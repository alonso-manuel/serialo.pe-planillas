<?php

namespace Database\Seeders;

use App\Enums\RegimenLaboral;
use App\Enums\RolUsuario;
use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Enums\TipoContrato;
use App\Models\User;

/**
 * Datos 100 % FICTICIOS dentro de un estudio de prueba (modo SaaS): las empresas que se le indiquen del
 * catálogo de abajo, con sus trabajadores y periodos (como el DemoSeeder). Usa el administrador del estudio;
 * no crea los usuarios demo@serialo.pe. RUC 200000000xx y DNI 7000xxxx inventados (Ley 29733).
 *
 * @phpstan-import-type EmpresaDemo from DemoSeeder
 */
class EstudioDemoSeeder extends DemoSeeder
{
    /** @var list<string> claves del catálogo de EMPRESAS */
    public array $empresasElegidas = [];

    /** @return array{0: User, 1: User|null} */
    protected function usuarios(): array
    {
        return [User::query()->where('rol', RolUsuario::Admin)->orderBy('id')->firstOrFail(), null];
    }

    protected function empresas(): array
    {
        $catalogo = [...$this->catalogoBase(), ...$this->catalogoAdicional()];

        return array_map(fn (string $clave) => $catalogo[$clave], $this->empresasElegidas);
    }

    /**
     * Las tres del DemoSeeder, con clave.
     *
     * @return array<string, EmpresaDemo>
     */
    private function catalogoBase(): array
    {
        $base = parent::empresas();

        return ['andes' => $base[0], 'huallaga' => $base[1], 'pacifico' => $base[2]];
    }

    /** @return array<string, EmpresaDemo> */
    private function catalogoAdicional(): array
    {
        $onp = SistemaPension::Onp;
        $afp = SistemaPension::Afp;
        $ind = TipoContrato::Indeterminado;
        $fijo = TipoContrato::PlazoFijo;

        return [
            'misti' => [
                'ruc' => '20000000052', 'razon_social' => 'Textiles Misti S.A.C.',
                'regimen' => RegimenLaboral::General, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Av. Imaginaria 210, Arequipa',
                'trabajadores' => [
                    ['Apaza', 'Condori', 'Elena', 'Jefa de producción', '3800.00', true, $afp, $ind],
                    ['Ticona', 'Mamani', 'Wilber', 'Operario textil', '1450.00', false, $onp, $fijo],
                    ['Zegarra', 'Rivera', 'Mónica', 'Diseñadora', '2600.00', false, $afp, $ind],
                    ['Huanca', 'Pari', 'Julio César', 'Mecánico de máquinas', '2100.00', true, $afp, $ind],
                ],
            ],
            'chira' => [
                'ruc' => '20000000061', 'razon_social' => 'Agroexportadora Chira S.A.C.',
                'regimen' => RegimenLaboral::PequenaEmpresa, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Carretera Ficticia km 12, Sullana',
                'trabajadores' => [
                    ['Seminario', 'Ruiz', 'Karina', 'Ingeniera agrónoma', '3500.00', true, $afp, $ind],
                    ['Chunga', 'Querevalú', 'Manuel', 'Capataz de campo', '1900.00', true, $onp, $ind],
                    ['Ancajima', 'Nima', 'Rosa', 'Seleccionadora', '1130.00', false, $afp, $fijo],
                    ['Alvarado', 'Saavedra', 'Iván', 'Chofer', '1500.00', false, $onp, $fijo],
                ],
            ],
            'espiga' => [
                'ruc' => '20000000079', 'razon_social' => 'Panificadora La Espiga E.I.R.L.',
                'regimen' => RegimenLaboral::Microempresa, 'seguro' => SeguroSalud::Sis,
                'direccion' => 'Jr. Supuesto 77, Trujillo',
                'trabajadores' => [
                    ['Alfaro', 'Cueva', 'Luz', 'Panadera', '1250.00', true, $onp, $ind],
                    ['Gutiérrez', 'Sánchez', 'Hugo', 'Ayudante de panadería', '1130.00', false, $afp, $fijo],
                    ['Rebaza', 'Lozano', 'Sandra', 'Vendedora', '1130.00', true, $afp, $fijo],
                ],
            ],
            'ucayali' => [
                'ruc' => '20000000087', 'razon_social' => 'Transportes Ucayali S.R.L.',
                'regimen' => RegimenLaboral::PequenaEmpresa, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Av. Inventada 1500, Pucallpa',
                'trabajadores' => [
                    ['Panduro', 'Ríos', 'Gloria', 'Administradora', '2900.00', true, $afp, $ind],
                    ['Fasabi', 'Tananta', 'Ronald', 'Conductor', '2000.00', true, $onp, $ind],
                    ['Shapiama', 'Ushiñahua', 'Edith', 'Despachadora', '1400.00', false, $afp, $fijo],
                    ['Vásquez', 'Pinedo', 'Alex', 'Mecánico', '1800.00', false, $afp, $ind],
                ],
            ],
            'sonrisa' => [
                'ruc' => '20000000095', 'razon_social' => 'Clínica Dental Sonrisa S.A.C.',
                'regimen' => RegimenLaboral::Microempresa, 'seguro' => SeguroSalud::Essalud,
                'direccion' => 'Calle Ejemplo 345, Cusco',
                'trabajadores' => [
                    ['Huamán', 'Quispe', 'Diana', 'Odontóloga', '3000.00', false, $afp, $ind],
                    ['Ccori', 'Mamani', 'Fredy', 'Asistente dental', '1300.00', true, $onp, $fijo],
                    ['Loayza', 'Farfán', 'Paola', 'Recepcionista', '1130.00', false, $afp, $fijo],
                ],
            ],
        ];
    }
}
