<?php

namespace Database\Factories;

use App\Enums\EstadoPeriodo;
use App\Models\Empresa;
use App\Models\Periodo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Periodo>
 */
class PeriodoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'empresa_id' => Empresa::factory(),
            'anio' => 2026,
            'mes' => fake()->unique()->numberBetween(1, 12),
            'estado' => EstadoPeriodo::Abierto,
        ];
    }

    public function cerrado(): static
    {
        return $this->state(fn (array $attributes) => [
            'estado' => EstadoPeriodo::Cerrado,
            'cerrado_en' => now(),
        ]);
    }
}
