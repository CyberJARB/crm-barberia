<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $nombre = fake()->company().' Barbería';

        return [
            'nombre' => $nombre,
            'slug' => Str::slug($nombre).'-'.fake()->unique()->numerify('###'),
            'email' => fake()->unique()->companyEmail(),
            'tipo' => 'barberia',
            'plan' => 'basico',
            'activo' => true,
            'pais' => 'DE',
            'moneda' => 'EUR',
            'zona_horaria' => 'Europe/Berlin',
        ];
    }
}
