<?php

namespace App\Console\Commands;

use App\Jobs\EnviarResumenTenant;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Tick horario: cada tenant elige su cadencia (mañana/3h/6h/12h) y este
 * comando decide, según la hora actual, a quién le toca el update.
 */
class EnviarResumenes extends Command
{
    protected $signature = 'emails:resumen
                              {--hora= : Hora 0-23 a evaluar (por defecto, la actual)}
                              {--dry-run : Mostrar a qué tenants se enviaría sin enviar nada}';

    protected $description = 'Despacha el resumen del negocio a los tenants cuya cadencia toca a esta hora';

    public function handle(): int
    {
        $hora = $this->option('hora') !== null
            ? (int) $this->option('hora')
            : now()->hour;

        // CWE-20 — rango validado (llega por CLI/scheduler)
        if ($hora < 0 || $hora > 23) {
            $this->error("Hora inválida: {$hora} (use 0-23)");

            return self::FAILURE;
        }

        // Cadencias a las que les toca envío a esta hora
        $frecuencias = array_keys(array_filter(
            Tenant::RESUMEN_HORAS,
            fn (array $horas): bool => in_array($hora, $horas, true),
        ));

        if ($frecuencias === []) {
            $this->info("A las {$hora}:00 no toca ningún envío.");

            return self::SUCCESS;
        }

        $tenants = Tenant::query()
            ->where('activo', true)
            ->where('resumen_activo', true)
            ->whereIn('resumen_frecuencia', $frecuencias)
            ->get();

        if ($tenants->isEmpty()) {
            $this->info('Ningún tenant con update activado para esta hora.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->warn('[--dry-run] Se enviaría a: '.$tenants->pluck('nombre')->implode(', '));

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            EnviarResumenTenant::dispatch($tenant);
        }

        $this->info("{$tenants->count()} resumen(es) despachados a la cola.");

        return self::SUCCESS;
    }
}
