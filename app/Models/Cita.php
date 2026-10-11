<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ReporteTexto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $cliente_id
 * @property int|null $personal_id
 * @property Carbon $fecha
 * @property string $hora_inicio
 * @property string|null $hora_fin
 * @property string $estado
 * @property string|null $notas
 * @property numeric-string|null $total
 * @property bool $recordatorio_enviado
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read Cliente|null $cliente
 * @property-read Personal|null $personal
 */
class Cita extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'cliente_id', 'personal_id', 'fecha', 'hora_inicio',
        'hora_fin', 'estado', 'notas', 'total', 'recordatorio_enviado',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
            'recordatorio_enviado' => 'boolean',
        ];
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

    /** @return BelongsTo<Personal, $this> */
    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    /** @return BelongsToMany<Servicio, $this> */
    public function servicios(): BelongsToMany
    {
        return $this->belongsToMany(Servicio::class, 'cita_servicios')
            ->withPivot(['precio', 'duracion_minutos'])
            ->withTimestamps();
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** @return HasMany<Recordatorio, $this> */
    public function recordatorios(): HasMany
    {
        return $this->hasMany(Recordatorio::class);
    }

    public function scopeDeHoy(Builder $query): Builder
    {
        return $query->whereDate('fecha', today());
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', ['pendiente', 'confirmada']);
    }

    /** Estados que ocupan la agenda del empleado (cuentan para solapes). */
    public const ESTADOS_ACTIVOS = ['pendiente', 'confirmada', 'en_progreso'];

    /**
     * Máquina de estados del ciclo de vida de una cita.
     * CWE-841 — Improper Enforcement of Behavioral Workflow: sin esto,
     * cualquier rol podría p.ej. "completar" una cita cancelada y generar cobros.
     */
    public const TRANSICIONES = [
        'pendiente' => ['confirmada', 'cancelada'],
        'confirmada' => ['en_progreso', 'completada', 'cancelada', 'no_show'],
        'en_progreso' => ['completada', 'cancelada'],
        'completada' => [],
        'cancelada' => [],
        'no_show' => [],
    ];

    public function estaCompletada(): bool
    {
        return $this->estado === 'completada';
    }

    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    public function puedeTransicionarA(string $nuevoEstado): bool
    {
        return in_array($nuevoEstado, self::TRANSICIONES[$this->estado] ?? [], true);
    }

    /**
     * Cambia el estado validando la transición y dejando rastro de auditoría
     * (OWASP A09). Lanza si la transición no es válida (CWE-841).
     */
    public function transicionarA(string $nuevoEstado): void
    {
        if (! $this->puedeTransicionarA($nuevoEstado)) {
            throw new \DomainException(
                "Übergang von '{$this->estado}' nach '{$nuevoEstado}' ist nicht erlaubt."
            );
        }

        $anterior = $this->estado;
        $this->update(['estado' => $nuevoEstado]);

        AuditLog::registrar(
            'CITA_ESTADO',
            'Cita',
            $this->id,
            ['estado' => $anterior],
            ['estado' => $nuevoEstado],
        );
    }

    /**
     * ¿El empleado ya tiene una cita activa que se solape con [inicio, fin)?
     * CWE-1284 — control de doble reserva; siempre acotado por tenant (OWASP A01).
     */
    public static function haySolape(
        int $tenantId,
        int $personalId,
        string $fecha,
        string $horaInicio,
        string $horaFin,
        ?int $ignorarCitaId = null,
    ): bool {
        return static::query()
            ->where('tenant_id', $tenantId)
            ->where('personal_id', $personalId)
            ->whereDate('fecha', $fecha)
            ->whereIn('estado', self::ESTADOS_ACTIVOS)
            ->when($ignorarCitaId !== null, fn (Builder $q) => $q->whereKeyNot($ignorarCitaId))
            // Solape de intervalos semiabiertos: inicioA < finB && inicioB < finA
            ->where('hora_inicio', '<', $horaFin)
            ->where('hora_fin', '>', $horaInicio)
            ->exists();
    }

    /**
     * Completa la cita y registra el cobro en una sola transacción.
     * El monto sale SIEMPRE del total de la cita (server-side), nunca del cliente.
     *
     * Con $vale: se canjea primero el vale (lock pesimista, CWE-362) y el
     * resto —si lo hay— se cobra con $metodo. Devuelve el pago principal.
     */
    public function cobrar(string $metodo, ?string $referencia = null, ?Vale $vale = null): Pago
    {
        // OWASP A04 / CWE-639 — método validado contra la allowlist del enum de BD
        if (! array_key_exists($metodo, ReporteTexto::metodos())) {
            throw new \InvalidArgumentException("Ungültige Zahlungsmethode: {$metodo}");
        }

        return DB::transaction(function () use ($metodo, $referencia, $vale) {
            $this->transicionarA('completada');

            $total = (float) $this->total;
            $pagoVale = null;

            if ($vale !== null && $total > 0) {
                // Relectura con lock dentro de la transacción: evita doble
                // canje concurrente y confirma tenant (OWASP A01 — el global
                // scope ya filtra, pero el whereKey explícito no estorba).
                $valeBloqueado = Vale::query()->whereKey($vale->id)->lockForUpdate()->first();

                if ($valeBloqueado === null || $valeBloqueado->tenant_id !== $this->tenant_id) {
                    throw new \DomainException('Gutschein nicht gefunden.');
                }
                if (! $valeBloqueado->esCanjeable()) {
                    throw new \DomainException('Gutschein ist nicht einlösbar (abgelaufen, storniert oder aufgebraucht).');
                }

                $aplicado = min((float) $valeBloqueado->saldo, $total);
                $valeBloqueado->descontar($aplicado);

                $pagoVale = $this->pagos()->create([
                    'tenant_id' => $this->tenant_id,
                    'cliente_id' => $this->cliente_id,
                    'vale_id' => $valeBloqueado->id,
                    'monto' => $aplicado,
                    'metodo' => 'vale',
                    'referencia' => $valeBloqueado->codigo,
                ]);

                $total = round($total - $aplicado, 2);
            }

            $pago = $pagoVale;

            if ($total > 0 || $pagoVale === null) {
                $pago = $this->pagos()->create([
                    'tenant_id' => $this->tenant_id,
                    'cliente_id' => $this->cliente_id,
                    'monto' => $total,
                    'metodo' => $metodo,
                    'referencia' => $referencia,
                ]);
            }

            AuditLog::registrar('CITA_COBRADA', 'Pago', $pago->id, null, [
                'cita_id' => $this->id,
                'monto' => (string) $this->total,
                'metodo' => $vale !== null ? "vale+{$metodo}" : $metodo,
            ]);

            // Estadísticas de fidelización del cliente
            $this->cliente?->increment('total_visitas');
            $this->cliente?->update(['ultima_visita' => now()]);

            return $pago;
        });
    }
}
