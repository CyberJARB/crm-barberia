<?php

namespace App\Console\Commands;

use App\Jobs\EnviarFelicitacionGeburtstag;
use App\Models\Cliente;
use Illuminate\Console\Command;

class FelicitacionesGeburtstag extends Command
{
    protected $signature = 'emails:geburtstage
                              {--dry-run : Mostrar a quién se felicitaría sin enviar nada}';

    protected $description = 'Despacha felicitaciones de cumpleaños a los clientes que cumplen años hoy (solo tenants con la opción activada)';

    public function handle(): int
    {
        $hoy = now();
        $dryRun = $this->option('dry-run');

        // Cruza todos los tenants — el job revalida flag, email y estado.
        // whereMonth/whereDay cubre también el 29.02 solo en años bisiestos.
        $query = Cliente::withoutGlobalScopes()
            ->whereMonth('fecha_nacimiento', $hoy->month)
            ->whereDay('fecha_nacimiento', $hoy->day)
            ->where('activo', true)
            ->whereNull('anonimizado_en')
            ->whereNotNull('email')
            ->whereHas('tenant', fn ($q) => $q
                ->where('activo', true)
                ->where('email_geburtstag_activo', true));

        $total = $query->count();

        if ($total === 0) {
            $this->info('Hoy no cumple años ningún cliente (con felicitación activada).');

            return self::SUCCESS;
        }

        $this->info("Encontrados {$total} cumpleaños para el {$hoy->format('d.m.')}.");

        if ($dryRun) {
            $this->warn('[--dry-run] No se despachó ningún job.');

            return self::SUCCESS;
        }

        $despachados = 0;
        $query->chunk(100, function ($clientes) use (&$despachados): void {
            foreach ($clientes as $cliente) {
                EnviarFelicitacionGeburtstag::dispatch($cliente);
                $despachados++;
            }
        });

        $this->info("{$despachados} felicitación(es) despachadas a la cola.");

        return self::SUCCESS;
    }
}
