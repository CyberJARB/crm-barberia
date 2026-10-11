<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\Vale;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vale>
 */
class ValeFactory extends Factory
{
    protected $model = Vale::class;

    public function definition(): array
    {
        $valor = $this->faker->randomElement([25, 50, 75, 100]);

        return [
            'tenant_id' => Tenant::factory(),
            'valor_inicial' => $valor,
            'saldo' => $valor,
            'valido_hasta' => now()->addYears(3),
        ];
    }

    public function caducado(): static
    {
        return $this->state(fn () => ['valido_hasta' => now()->subDay()]);
    }

    public function anulado(): static
    {
        return $this->state(fn () => ['anulado_en' => now()]);
    }

    public function agotado(): static
    {
        return $this->state(fn () => ['saldo' => 0]);
    }
}
