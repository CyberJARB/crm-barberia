<?php

namespace App\Console\Commands;

use App\Models\ListaEspera;
use Illuminate\Console\Command;

class ExpirarListaEspera extends Command
{
    protected $signature = 'warteliste:expirar';

    protected $description = 'Marca como expiradas las entradas de la Warteliste cuya fecha deseada ya pasó';

    public function handle(): int
    {
        // Cruza todos los tenants: tarea de mantenimiento del sistema
        $expiradas = ListaEspera::withoutGlobalScopes()
            ->whereIn('estado', ['esperando', 'avisado'])
            ->whereDate('fecha_deseada', '<', today())
            ->update(['estado' => 'expirado']);

        $this->info("{$expiradas} entrada(s) de la Warteliste expiradas.");

        return self::SUCCESS;
    }
}
