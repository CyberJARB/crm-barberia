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
 * @property int $id
 * @property int $tenant_id
 * @property int|null $cita_id
 * @property int|null $cliente_id
 * @property int|null $vale_id
 * @property int|null $producto_id
 * @property numeric-string $monto
 * @property string $metodo
 * @property string|null $referencia
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read Cita|null $cita
 * @property-read Cliente|null $cliente
 */
class Pago extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'cita_id', 'cliente_id', 'vale_id', 'producto_id',
        'monto', 'metodo', 'referencia', 'notas',
    ];

    protected function casts(): array
    {
        return ['monto' => 'decimal:2'];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return BelongsTo<Cita, $this> */
    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    /** @return BelongsTo<Cliente, $this> */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /** @return BelongsTo<Vale, $this> */
    public function vale(): BelongsTo
    {
        return $this->belongsTo(Vale::class);
    }

    /** @return BelongsTo<Producto, $this> */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function scopeDeHoy(Builder $query): Builder
    {
        return $query->whereDate('created_at', today());
    }
}
