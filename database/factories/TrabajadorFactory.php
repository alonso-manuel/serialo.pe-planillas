<?php

namespace Database\Factories;

use App\Enums\SeguroSalud;
use App\Enums\SistemaPension;
use App\Models\Empresa;
use App\Models\Trabajador;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Trabajadores 100 % ficticios (DNI 7xxxxxxx sin correspondencia real; Ley 29733).
 *
 * @extends Factory<Trabajador>
 */
class TrabajadorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'tipo_documento' => '01',
            'numero_documento' => fake()->unique()->numerify('7#######'),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'nombres' => fake()->firstName(),
            'fecha_ingreso' => now()->subYear()->startOfMonth(),
            'remuneracion_basica' => '1500.00',
            'asignacion_familiar' => false,
            'sistema_pension' => SistemaPension::Onp,
            'seguro_salud' => SeguroSalud::Essalud,
        ];
    }
}
