<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recordatorio extends Model
{
    protected $fillable = [
        'cita_id', 'tipo', 'programado_para', 'enviado_at', 'estado', 'error',
    ];

    protected function casts(): array
    {
        return [
            'programado_para' => 'datetime',
            'enviado_at' => 'datetime',
        ];
    }

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'pendiente')
            ->where('programado_para', '<=', now());
    }
}
