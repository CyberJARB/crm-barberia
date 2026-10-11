<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasEncryptedFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property Carbon|null $fecha_nacimiento
 * @property string|null $notas
 * @property string|null $alergias
 * @property bool $mensajeria_optin
 * @property Carbon|null $mensajeria_optin_en
 * @property string $mensajeria_canal
 * @property int $total_visitas
 * @property Carbon|null $ultima_visita
 * @property bool $activo
 * @property Carbon|null $anonimizado_en
 * @property-read Tenant|null $tenant
 */
class Cliente extends Model
{
    use BelongsToTenant, HasEncryptedFields, HasFactory, SoftDeletes;

    /**
     * OWASP A02 / GDPR Art. 25 — Privacy by Design
     * Campos PII cifrados en reposo con AES-256 (Laravel Crypt).
     */
    protected array $encryptedFields = ['telefono', 'alergias', 'notas'];

    protected $fillable = [
        'tenant_id', 'nombre', 'telefono', 'email',
        'fecha_nacimiento', 'notas', 'alergias',
        'mensajeria_optin', 'mensajeria_canal',
        'total_visitas', 'ultima_visita', 'activo', 'anonimizado_en',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'ultima_visita' => 'datetime',
            'activo' => 'boolean',
            'anonimizado_en' => 'datetime',
            'mensajeria_optin' => 'boolean',
            'mensajeria_optin_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // §7 UWG / DSGVO Art. 7 — el consentimiento de mensajería se
        // timestampea y audita automáticamente (prueba de opt-in y de
        // revocación; el timestamp no es editable desde ningún form).
        static::saving(function (Cliente $cliente) {
            if ($cliente->isDirty('mensajeria_optin')) {
                $cliente->mensajeria_optin_en = $cliente->mensajeria_optin ? now() : null;
            }
        });

        static::saved(function (Cliente $cliente) {
            if ($cliente->wasChanged('mensajeria_optin')) {
                AuditLog::registrar(
                    $cliente->mensajeria_optin ? 'CONSENTIMIENTO_MENSAJERIA' : 'REVOCACION_MENSAJERIA',
                    'Cliente',
                    $cliente->id,
                    null,
                    ['canal' => $cliente->mensajeria_canal],
                );
            }
        });
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasMany<Cita, $this> */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    /** @return HasMany<Pago, $this> */
    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class);
    }

    /** @return HasMany<Vale, $this> */
    public function vales(): HasMany
    {
        return $this->hasMany(Vale::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function getInicialAttribute(): string
    {
        return strtoupper(substr($this->nombre, 0, 1));
    }

    /**
     * GDPR Art. 17 — Derecho al Olvido
     * Sustituye todos los PII por datos anónimos, preservando el registro
     * para la integridad del historial de auditoría (ISO 27001 A.12.4).
     */
    public function anonimizar(): void
    {
        $this->update([
            'nombre' => 'Cliente Anonimizado #'.$this->id,
            'email' => 'anonimizado_'.$this->id.'@eliminado.local',
            'telefono' => null,
            'fecha_nacimiento' => null,
            'notas' => null,
            'alergias' => null,
            'mensajeria_optin' => false,
            'activo' => false,
            'anonimizado_en' => now(),
        ]);

        AuditLog::registrar('GDPR_BORRADO', 'Cliente', $this->id, null, [
            'motivo' => 'Derecho al olvido GDPR Art.17',
        ], 'ok');
    }
}
