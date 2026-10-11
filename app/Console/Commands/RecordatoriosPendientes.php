<?php

namespace App\Console\Commands;

use App\Jobs\EnviarRecordatorioCita;
use App\Models\Cita;
use Illuminate\Console\Command;

class RecordatoriosPendientes extends Command
{
    protected $signature = 'citas:recordatorios-pendientes
                              {--dry-run : Mostrar qué se enviaría sin enviar nada}';

    protected $description = 'Despacha recordatorios para citas de mañana con recordatorio_enviado=false (rescate de cola caída)';

    public function handle(): int
    {
        $manana = now()->addDay()->toDateString();
        $dryRun = $this->option('dry-run');

        $query = Cita::withoutGlobalScopes()
            ->whereDate('fecha', $manana)
            ->where('recordatorio_enviado', false)
            ->whereNotIn('estado', ['cancelada'])
            ->with(['tenant']);

        $total = $query->count();

        if ($total === 0) {
            $this->info('No hay recordatorios pendientes para mañana.');

            return self::SUCCESS;
        }

        $this->info("Encontradas {$total} cita(s) sin recordatorio para el {$manana}.");

        if ($dryRun) {
            $this->warn('[--dry-run] No se despachó ningún job.');

            return self::SUCCESS;
        }

        $despachados = 0;
        $query->chunk(100, function ($citas) use (&$despachados): void {
            foreach ($citas as $cita) {
                if (! $cita->tenant->email_recordatorio_activo) {
                    continue;
                }
                EnviarRecordatorioCita::dispatch($cita);
                $despachados++;
            }
        });

        $this->info("{$despachados} job(s) despachados a la cola.");

        return self::SUCCESS;
    }
}
