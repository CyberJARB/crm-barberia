<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Genera (o rota) la API key pública de un tenant.
 * La clave en claro se muestra UNA sola vez; en BD solo vive su SHA-256.
 *
 *   php artisan tenant:api-key 1
 */
class GenerarApiKeyTenant extends Command
{
    protected $signature = 'tenant:api-key {tenant : ID del tenant}';

    protected $description = 'Genera o rota la API key pública de reservas de un tenant';

    public function handle(): int
    {
        $tenant = Tenant::find($this->argument('tenant'));

        if (! $tenant) {
            $this->error('Tenant no encontrado.');

            return self::FAILURE;
        }

        $clave = 'pk_'.Str::random(40);
        $rotada = $tenant->api_key_hash !== null;

        // forceFill: api_key_hash queda fuera de $fillable a propósito
        // (no debe ser asignable en masa desde formularios del panel)
        $tenant->forceFill(['api_key_hash' => hash('sha256', $clave)])->save();

        AuditLog::registrar(
            $rotada ? 'API_KEY_ROTADA' : 'API_KEY_GENERADA',
            'Tenant',
            $tenant->id,
        );

        $this->info("API key para «{$tenant->nombre}» (guárdala ahora, no se mostrará de nuevo):");
        $this->newLine();
        $this->line("  {$clave}");
        $this->newLine();
        $this->comment('Úsala en el sitio web como header X-API-Key.');

        return self::SUCCESS;
    }
}
