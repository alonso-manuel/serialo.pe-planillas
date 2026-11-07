<?php

namespace Database\Factories;

use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Empresas 100 % ficticias: RUC con prefijo 20000 (no corresponde a contribuyentes reales).
 *
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ruc' => '20000'.fake()->unique()->numerify('######'),
            'razon_social' => fake()->unique()->company().' S.A.C.',
            'direccion' => 'Av. Ficticia '.fake()->numberBetween(100, 999),
            'regimen_laboral' => RegimenLaboral::General,
            'seguro_salud' => SeguroSalud::Essalud,
            'activo' => true,
        ];
    }

    public function inactiva(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    public function microempresa(): static
    {
        return $this->state(fn (array $attributes) => [
            'regimen_laboral' => RegimenLaboral::Microempresa,
            'seguro_salud' => SeguroSalud::Sis,
        ]);
    }
}
