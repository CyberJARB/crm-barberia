<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * NIST CSF Respond — Respuesta a incidentes de seguridad.
 * Bloquea TODOS los usuarios excepto el admin de emergencia.
 * Uso: php artisan seguridad:lockdown --admin=admin@crm-barberia.de
 * Para revertir: php artisan seguridad:lockdown --revertir
 */
class Lockdown extends Command
{
    protected $signature = 'seguridad:lockdown
                             {--admin= : Email del admin que seguirá con acceso}
                             {--revertir : Revertir el lockdown y reactivar todos los usuarios}';

    protected $description = 'Bloqueo de emergencia: desactiva todos los usuarios';

    public function handle(): int
    {
        if ($this->option('revertir')) {
            return $this->revertir();
        }

        $adminEmail = $this->option('admin');

        if (! $this->confirm(
            '⚠ ATENCIÓN: Esto bloqueará TODOS los usuarios'.
            ($adminEmail ? " excepto {$adminEmail}" : '').
            '. ¿Continuar?',
            false
        )) {
            $this->info('Lockdown cancelado.');

            return self::SUCCESS;
        }

        $query = User::query();

        if ($adminEmail) {
            $query->where('email', '!=', $adminEmail);
        }

        $afectados = $query->count();
        $query->update(['activo' => false]);

        // Invalidar todas las sesiones activas (CWE-613)
        DB::table('sessions')->when($adminEmail, function ($q) use ($adminEmail) {
            $adminId = User::where('email', $adminEmail)->value('id');

            return $q->where('user_id', '!=', $adminId);
        })->delete();

        Log::channel('security')->alert('LOCKDOWN_ACTIVADO', [
            'admin_preservado' => $adminEmail,
            'usuarios_bloqueados' => $afectados,
            'ip' => '(consola)',
        ]);

        AuditLog::create([
            'user_id' => User::where('email', $adminEmail)->value('id'),
            'tenant_id' => null,
            'accion' => 'LOCKDOWN',
            'entidad' => 'Sistema',
            'ip' => '127.0.0.1',
            'user_agent' => 'CLI',
            'resultado' => 'ok',
            'valores_nuevos' => json_encode(['usuarios_bloqueados' => $afectados]),
        ]);

        $this->error("LOCKDOWN ACTIVO — {$afectados} usuarios bloqueados.");
        $this->warn('Para revertir: php artisan seguridad:lockdown --revertir');

        return self::SUCCESS;
    }

    private function revertir(): int
    {
        if (! $this->confirm('¿Reactivar todos los usuarios?', false)) {
            return self::SUCCESS;
        }

        $afectados = User::where('activo', false)->count();
        User::where('activo', false)->update(['activo' => true]);

        Log::channel('security')->info('LOCKDOWN_REVERTIDO', [
            'usuarios_reactivados' => $afectados,
        ]);

        $this->info("Lockdown revertido — {$afectados} usuarios reactivados.");

        return self::SUCCESS;
    }
}
