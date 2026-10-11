<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $nombre
 * @property string|null $descripcion
 * @property int $duracion_minutos
 * @property numeric-string $precio
 * @property string|null $categoria
 * @property string|null $color
 * @property bool $activo
 * @property-read Tenant|null $tenant
 */
class Servicio extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'nombre', 'descripcion', 'duracion_minutos',
        'precio', 'categoria', 'color', 'activo',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsToMany<Cita, $this> */
    public function citas(): BelongsToMany
    {
        return $this->belongsToMany(Cita::class, 'cita_servicios')
            ->withPivot(['precio', 'duracion_minutos'])
            ->withTimestamps();
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function getDuracionFormateadaAttribute(): string
    {
        $h = intdiv($this->duracion_minutos, 60);
        $m = $this->duracion_minutos % 60;

        return $h > 0 ? "{$h}h {$m}min" : "{$m}min";
    }
}
