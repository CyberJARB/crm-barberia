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
 * Entrada de la Warteliste.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $cliente_id
 * @property int|null $servicio_id
 * @property Carbon $fecha_deseada
 * @property string $franja
 * @property string $estado
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read Cliente|null $cliente
 * @property-read Servicio|null $servicio
 */
class ListaEspera extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'lista_espera';

    public const FRANJAS = [
        'manana' => 'Vormittag',
        'tarde' => 'Nachmittag',
        'cualquiera' => 'Egal',
    ];

    public const ESTADOS = [
        'esperando' => 'Wartet',
        'avisado' => 'Benachrichtigt',
        'convertido' => 'Termin gebucht',
        'expirado' => 'Abgelaufen',
    ];

    protected $fillable = [
        'tenant_id', 'cliente_id', 'servicio_id',
        'fecha_deseada', 'franja', 'estado', 'notas',
    ];

    protected function casts(): array
    {
        return ['fecha_deseada' => 'date'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Servicio, $this> */
    public function servicio(): BelongsTo
    {
        return $this->belongsTo(Servicio::class);
    }

    public function scopeEsperando(Builder $query): Builder
    {
        return $query->where('estado', 'esperando');
    }

    /**
     * Cuántos esperan un hueco para esa fecha en este tenant
     * (para avisar al liberar un hueco por cancelación).
     */
    public static function esperandoPara(int $tenantId, string $fecha): int
    {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('fecha_deseada', $fecha)
            ->esperando()
            ->count();
    }
}
