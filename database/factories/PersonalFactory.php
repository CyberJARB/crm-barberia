<?php

namespace Database\Factories;

use App\Models\Personal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Personal>
 */
class PersonalFactory extends Factory
{
    protected $model = Personal::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => fake()->name(),
            'telefono' => fake()->numerify('+49 ### #######'),
            'email' => fake()->unique()->safeEmail(),
            'color' => fake()->hexColor(),
            'activo' => true,
        ];
    }
}
