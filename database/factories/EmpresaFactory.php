<?php

namespace Database\Factories;

use App\Domain\Sunat\Ruc;
use App\Enums\RegimenLaboral;
use App\Enums\SeguroSalud;
use App\Models\Empresa;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Empresas 100 % ficticias: RUC 2099xxxxxx + dígito verificador válido.
 *
 * @extends Factory<Empresa>
 */
class EmpresaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ruc' => Ruc::completar('20'.fake()->unique()->numerify('99######')),
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
