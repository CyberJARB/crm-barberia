<?php

namespace App\Events;

use App\Models\Cita;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CitaCreada
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Cita $cita) {}
}
