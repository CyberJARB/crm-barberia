<?php

namespace App\Console\Commands;

use App\Support\AnomalyDetector;
use Illuminate\Console\Command;

/**
 * OWASP A09 / NIST CSF Detect+Respond
 * Ejecutar cada 5 minutos via scheduler.
 */
class DetectarAnomalias extends Command
{
    protected $signature = 'seguridad:detectar-anomalias';

    protected $description = 'Analiza logs de auditoría para detectar patrones anómalos';

    public function handle(): int
    {
        $alertas = AnomalyDetector::analizar();

        if (empty($alertas)) {
            $this->info('Sin anomalías detectadas.');
        } else {
            foreach ($alertas as $alerta) {
                $this->warn("ALERTA [{$alerta['tipo']}]: ".json_encode($alerta));
            }
            $this->error(count($alertas).' anomalía(s) detectada(s). Ver security.log.');
        }

        return self::SUCCESS;
    }
}
