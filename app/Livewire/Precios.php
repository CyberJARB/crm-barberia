<?php

namespace App\Livewire;

use App\Support\Planes;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Página pública de precios (/precios) — alemán, 3 planes.
 * El checkout real se inicia con POST a stripe.checkout (auth + throttle).
 */
class Precios extends Component
{
    public function render(): View
    {
        return view('livewire.precios', [
            'planes' => Planes::todos(),
        ])->layout('layouts.publico', ['title' => 'Preise — CRM Barberías']);
    }
}
