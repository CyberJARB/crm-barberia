<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CrearSuperAdmin extends Command
{
    protected $signature = 'superadmin:crear {email} {--name=Betreiber}';

    protected $description = 'Crea (o resetea la contraseña de) la cuenta del operador del SaaS para el panel /super';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('E-Mail inválido.');

            return self::FAILURE;
        }

        // CWE-521 — contraseña aleatoria fuerte, mostrada una sola vez
        $password = Str::password(20);

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => (string) $this->option('name'),
                'password' => Hash::make($password),
                'tenant_id' => null,
                'rol' => 'superadmin',
                'activo' => true,
                'debe_cambiar_password' => false,
            ],
        );

        AuditLog::registrar('SAAS_SUPERADMIN_CREADO', 'User', $user->id, null, ['email' => $email]);

        $this->info('Cuenta de operador lista:');
        $this->line('  URL:      /super/login');
        $this->line("  E-Mail:   {$email}");
        $this->line("  Password: {$password}");
        $this->warn('Guarda la contraseña ahora — no se volverá a mostrar. Activa 2FA en el primer login.');

        return self::SUCCESS;
    }
}
