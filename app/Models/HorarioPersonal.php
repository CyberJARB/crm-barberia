<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $personal_id
 * @property int $dia_semana
 * @property string $hora_inicio
 * @property string $hora_fin
 * @property bool $activo
 */
class HorarioPersonal extends Model
{
    // La migración crea 'horarios_personal'; la pluralización automática no coincide
    protected $table = 'horarios_personal';

    protected $fillable = [
        'personal_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function getNombreDiaAttribute(): string
    {
        $dias = ['Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];

        return $dias[$this->dia_semana] ?? '';
    }
}
