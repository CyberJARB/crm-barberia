<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Nunca hardcodear credenciales (CWE-798): se toma de .env o se genera una aleatoria.
        $password = env('ADMIN_SEED_PASSWORD') ?: Str::password(20);

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-barberia'],
            [
                'nombre' => 'Demo Barbería',
                'tipo' => 'barberia',
                'telefono' => '+49 40 000000',
                'email' => 'demo@crm-barberia.de',
                'ciudad' => 'Hamburg',
                'pais' => 'DE',
                'plan' => 'profesional',
                'activo' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'admin@crm-barberia.de'],
            [
                'name' => 'Admin',
                'password' => Hash::make($password),
                'tenant_id' => $tenant->id,
                'rol' => 'propietario',
                'activo' => true,
            ]
        );

        $this->command->info('Admin creado: admin@crm-barberia.de');
        if (! env('ADMIN_SEED_PASSWORD')) {
            $this->command->warn("Contraseña generada (guárdala, no se vuelve a mostrar): {$password}");
        }
    }
}
