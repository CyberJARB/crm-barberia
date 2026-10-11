<?php

namespace Database\Factories;

use App\Models\Producto;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => $this->faker->randomElement(['Pomade', 'Shampoo', 'Bartöl', 'Haarwachs']).' '.$this->faker->word(),
            'categoria' => $this->faker->randomElement(['Styling', 'Pflege', 'Bart']),
            'precio' => $this->faker->randomFloat(2, 5, 40),
            'stock' => $this->faker->numberBetween(5, 50),
            'stock_minimo' => 3,
            'activo' => true,
        ];
    }

    public function sinStock(): static
    {
        return $this->state(fn () => ['stock' => 0]);
    }
}
