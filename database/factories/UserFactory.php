<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('Test#Clave2026!'),
            'remember_token' => Str::random(10),
            'tenant_id' => Tenant::factory(),
            'rol' => 'propietario',
            'activo' => true,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'debe_cambiar_password' => false,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function bloqueado(): static
    {
        return $this->state(fn (array $attributes) => [
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->addMinutes(15),
        ]);
    }
}
