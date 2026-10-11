<?php

namespace App\Observers;

use App\Events\CitaCreada;
use App\Models\Cita;

class CitaObserver
{
    public function created(Cita $cita): void
    {
        if (in_array($cita->estado, ['pendiente', 'confirmada'])) {
            CitaCreada::dispatch($cita);
        }
    }
}
