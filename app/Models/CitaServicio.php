<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CitaServicio extends Model
{
    protected $table = 'cita_servicios';

    protected $fillable = [
        'cita_id', 'servicio_id', 'precio', 'duracion_minutos',
    ];

    protected function casts(): array
    {
        return ['precio' => 'decimal:2'];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }
}
