<?php

namespace Database\Factories;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cita>
 */
class CitaFactory extends Factory
{
    protected $model = Cita::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'cliente_id' => Cliente::factory(),
            'personal_id' => null,
            'fecha' => fake()->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d'),
            'hora_inicio' => fake()->time('H:i:s', '18:00:00'),
            'hora_fin' => fake()->time('H:i:s', '19:00:00'),
            'estado' => 'confirmada',
            'total' => fake()->randomFloat(2, 10, 80),
            'recordatorio_enviado' => false,
        ];
    }

    public function pendiente(): static
    {
        return $this->state(['estado' => 'pendiente']);
    }

    public function cancelada(): static
    {
        return $this->state(['estado' => 'cancelada']);
    }

    public function recordatorioEnviado(): static
    {
        return $this->state(['recordatorio_enviado' => true]);
    }
}
