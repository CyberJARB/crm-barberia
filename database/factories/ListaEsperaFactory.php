<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\ListaEspera;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ListaEspera>
 */
class ListaEsperaFactory extends Factory
{
    protected $model = ListaEspera::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cliente_id' => Cliente::factory(),
            'fecha_deseada' => now()->addDays($this->faker->numberBetween(0, 14)),
            'franja' => $this->faker->randomElement(['manana', 'tarde', 'cualquiera']),
            'estado' => 'esperando',
        ];
    }
}
