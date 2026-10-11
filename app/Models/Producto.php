<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ReporteTexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

/**
 * Producto de venta (Produkt).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nombre
 * @property string|null $categoria
 * @property numeric-string $precio
 * @property int $stock
 * @property int $stock_minimo
 * @property bool $activo
 * @property string|null $descripcion
 * @property-read Tenant|null $tenant
 */
class Producto extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'nombre', 'categoria', 'precio',
        'stock', 'stock_minimo', 'activo', 'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'stock' => 'integer',
            'stock_minimo' => 'integer',
            'activo' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeStockBajo(Builder $query): Builder
    {
        return $query->whereColumn('stock', '<=', 'stock_minimo');
    }

    public function tieneStockBajo(): bool
    {
        return $this->stock <= $this->stock_minimo;
    }

    /**
     * Vende N unidades: decrementa stock y registra el pago en una sola
     * transacción con lock pesimista (CWE-362 — sin ventas concurrentes
     * del último stock). El precio sale SIEMPRE de la BD (CWE-602).
     */
    public function vender(int $cantidad, string $metodo, ?Cliente $cliente = null): Pago
    {
        // OWASP A04 / CWE-639 — método validado contra la allowlist
        if (! array_key_exists($metodo, ReporteTexto::metodos())) {
            throw new \InvalidArgumentException("Ungültige Zahlungsmethode: {$metodo}");
        }

        if ($cantidad < 1 || $cantidad > 999) {
            throw new \DomainException('Ungültige Menge.');
        }

        return DB::transaction(function () use ($cantidad, $metodo, $cliente) {
            /** @var self $producto */
            $producto = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if (! $producto->activo) {
                throw new \DomainException('Produkt ist nicht aktiv.');
            }
            if ($producto->stock < $cantidad) {
                throw new \DomainException("Nicht genug Lagerbestand (verfügbar: {$producto->stock}).");
            }

            $producto->decrement('stock', $cantidad);

            $pago = Pago::create([
                'tenant_id' => $producto->tenant_id,
                'cliente_id' => $cliente?->id,
                'producto_id' => $producto->id,
                'monto' => round((float) $producto->precio * $cantidad, 2),
                'metodo' => $metodo,
                'referencia' => "{$cantidad}x {$producto->nombre}",
                'notas' => 'Produktverkauf',
            ]);

            AuditLog::registrar('PRODUKT_VERKAUFT', 'Producto', $producto->id, null, [
                'cantidad' => $cantidad,
                'monto' => (string) $pago->monto,
                'stock_restante' => $producto->stock,
            ]);

            return $pago;
        });
    }
}
