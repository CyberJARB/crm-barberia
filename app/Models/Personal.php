<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $user_id
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $color
 * @property bool $activo
 * @property-read Tenant|null $tenant
 */
class Personal extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    // La migración crea 'personal' (singular); sin esto Eloquent buscaría 'personals'
    protected $table = 'personal';

    protected $fillable = [
        'tenant_id', 'user_id', 'nombre', 'telefono', 'email', 'color', 'activo',
    ];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return HasMany<HorarioPersonal, $this> */
    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioPersonal::class);
    }

    /** @return HasMany<Ausencia, $this> */
    public function ausencias(): HasMany
    {
        return $this->hasMany(Ausencia::class);
    }

    /** Sin ausencia (vacaciones/baja) que cubra la fecha dada (Y-m-d). */
    public function scopeDisponiblesEl(Builder $query, string $fecha): Builder
    {
        return $query->whereDoesntHave('ausencias', fn (Builder $q) => $q
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha));
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
