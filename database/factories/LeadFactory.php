<?php

namespace Database\Factories;

use App\Models\Lead;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    protected $model = Lead::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'nombre' => $this->faker->name(),
            'telefono' => '+49170'.$this->faker->numerify('#######'),
            'email' => $this->faker->safeEmail(),
            'mensaje' => $this->faker->sentence(),
            'fuente' => $this->faker->randomElement(array_keys(Lead::FUENTES)),
            'estado' => 'nuevo',
            'valor_estimado' => $this->faker->optional()->randomFloat(2, 20, 5000),
            'mensajeria_optin' => $this->faker->boolean(),
        ];
    }
}
