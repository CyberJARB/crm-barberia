<?php

namespace Database\Factories;

use App\Models\Ausencia;
use App\Models\Personal;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ausencia>
 */
class AusenciaFactory extends Factory
{
    protected $model = Ausencia::class;

    public function definition(): array
    {
        $inicio = now()->addDays($this->faker->numberBetween(1, 20));

        return [
            'tenant_id' => Tenant::factory(),
            'personal_id' => Personal::factory(),
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $inicio->copy()->addDays($this->faker->numberBetween(0, 10))->toDateString(),
            'motivo' => $this->faker->randomElement(['vacaciones', 'enfermedad', 'otro']),
        ];
    }
}
