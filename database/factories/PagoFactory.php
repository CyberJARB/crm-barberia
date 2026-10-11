<?php

namespace Database\Factories;

use App\Models\Pago;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pago>
 */
class PagoFactory extends Factory
{
    protected $model = Pago::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'monto' => fake()->randomFloat(2, 10, 200),
            'metodo' => fake()->randomElement(['efectivo', 'tarjeta', 'transferencia']),
        ];
    }
}
