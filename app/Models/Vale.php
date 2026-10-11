<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Vale regalo (Gutschein).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $codigo
 * @property numeric-string $valor_inicial
 * @property numeric-string $saldo
 * @property int|null $cliente_id
 * @property Carbon|null $valido_hasta
 * @property Carbon|null $anulado_en
 * @property string|null $notas
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read Cliente|null $cliente
 */
class Vale extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    /** Sin caracteres ambiguos (0/O, 1/I/L) para dictado por teléfono. */
    private const ALFABETO_CODIGO = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    protected $fillable = [
        'tenant_id', 'codigo', 'valor_inicial', 'saldo',
        'cliente_id', 'valido_hasta', 'anulado_en', 'notas',
    ];

    protected function casts(): array
    {
        return [
            'valor_inicial' => 'decimal:2',
            'saldo' => 'decimal:2',
            'valido_hasta' => 'date',
            'anulado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Vale $vale) {
            if (empty($vale->codigo)) {
                $vale->codigo = self::generarCodigo((int) $vale->tenant_id);
            }
            if ($vale->getAttribute('saldo') === null) {
                $vale->saldo = $vale->valor_inicial;
            }
        });
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

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /**
     * Código tipo GS-K7M2-9XQ4 con CSPRNG (random_int, CWE-338) y
     * verificación de unicidad dentro del tenant.
     */
    public static function generarCodigo(int $tenantId): string
    {
        do {
            $bloques = [];
            for ($b = 0; $b < 2; $b++) {
                $bloque = '';
                for ($i = 0; $i < 4; $i++) {
                    $bloque .= self::ALFABETO_CODIGO[random_int(0, strlen(self::ALFABETO_CODIGO) - 1)];
                }
                $bloques[] = $bloque;
            }
            $codigo = 'GS-'.implode('-', $bloques);

            $existe = static::withoutGlobalScope('tenant')
                ->withTrashed()
                ->where('tenant_id', $tenantId)
                ->where('codigo', $codigo)
                ->exists();
        } while ($existe);

        return $codigo;
    }

    public function estaCaducado(): bool
    {
        return $this->valido_hasta !== null && $this->valido_hasta->isPast()
            && ! $this->valido_hasta->isToday();
    }

    public function estaAnulado(): bool
    {
        return $this->anulado_en !== null;
    }

    public function esCanjeable(): bool
    {
        return ! $this->estaAnulado()
            && ! $this->estaCaducado()
            && (float) $this->saldo > 0;
    }

    /** Estado para la UI: clave interna => se traduce en la vista. */
    public function estado(): string
    {
        return match (true) {
            $this->estaAnulado() => 'anulado',
            $this->estaCaducado() => 'caducado',
            (float) $this->saldo <= 0 => 'agotado',
            default => 'activo',
        };
    }

    /**
     * Descuenta saldo de forma atómica. Debe llamarse DENTRO de una
     * transacción sobre una fila ya bloqueada (lockForUpdate) para evitar
     * doble canje concurrente (CWE-362).
     */
    public function descontar(float $monto): void
    {
        if ($monto <= 0 || $monto > (float) $this->saldo) {
            throw new \DomainException('Gutschein-Guthaben reicht nicht aus.');
        }

        $this->update(['saldo' => number_format((float) $this->saldo - $monto, 2, '.', '')]);

        AuditLog::registrar('VALE_CANJEADO', 'Vale', $this->id, null, [
            'codigo' => $this->codigo,
            'monto' => number_format($monto, 2, '.', ''),
            'saldo_restante' => $this->saldo,
        ]);
    }

    /**
     * Anula el vale (p. ej. emitido por error). Irreversible y auditado.
     */
    public function anular(): void
    {
        $this->update(['anulado_en' => now()]);

        AuditLog::registrar('VALE_ANULADO', 'Vale', $this->id, null, [
            'codigo' => $this->codigo,
            'saldo_al_anular' => $this->saldo,
        ]);
    }
}
