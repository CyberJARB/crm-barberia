<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Abwesenheit del personal (vacaciones, enfermedad…).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $personal_id
 * @property Carbon $fecha_inicio
 * @property Carbon $fecha_fin
 * @property string $motivo
 * @property string|null $notas
 * @property-read Tenant|null $tenant
 * @property-read Personal|null $personal
 */
class Ausencia extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const MOTIVOS = [
        'vacaciones' => 'Urlaub',
        'enfermedad' => 'Krankheit',
        'otro' => 'Sonstiges',
    ];

    protected $fillable = [
        'tenant_id', 'personal_id', 'fecha_inicio', 'fecha_fin', 'motivo', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    /** Ausencias que cubren la fecha dada (Y-m-d). */
    public function scopeCubre(Builder $query, string $fecha): Builder
    {
        return $query
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha);
    }

    /** ¿El empleado está ausente ese día? Siempre acotado por tenant (OWASP A01). */
    public static function estaAusente(int $tenantId, int $personalId, string $fecha): bool
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('personal_id', $personalId)
            ->cubre($fecha)
            ->exists();
    }
}
