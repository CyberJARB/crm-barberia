<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasEncryptedFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Lead / Interessent — entrada del embudo de ventas (multi-sector).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $nombre
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $mensaje
 * @property string $fuente
 * @property string $estado
 * @property float|null $valor_estimado
 * @property string|null $motivo_perdida
 * @property int|null $asignado_a
 * @property int|null $cliente_id
 * @property bool $mensajeria_optin
 * @property Carbon|null $contactado_en
 * @property Carbon|null $cerrado_en
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property Carbon|null $created_at
 * @property-read Tenant|null $tenant
 * @property-read Cliente|null $cliente
 * @property-read User|null $responsable
 */
class Lead extends Model
{
    use BelongsToTenant, HasEncryptedFields, HasFactory, SoftDeletes;

    /** OWASP A02 / GDPR Art. 25 — PII de interesados cifrada en reposo. */
    protected array $encryptedFields = ['telefono', 'mensaje'];

    /** Columnas del embudo (clave interna => etiqueta alemana). */
    public const ESTADOS = [
        'nuevo' => 'Neu',
        'contactado' => 'Kontaktiert',
        'cualificado' => 'Qualifiziert',
        'propuesta' => 'Angebot',
        'ganado' => 'Gewonnen',
        'perdido' => 'Verloren',
    ];

    public const FUENTES = [
        'web' => 'Website',
        'whatsapp' => 'WhatsApp',
        'telefono' => 'Telefon',
        'instagram' => 'Instagram',
        'empfehlung' => 'Empfehlung',
        'n8n' => 'Automatisierung',
        'manual' => 'Manuell',
    ];

    public const MOTIVOS_PERDIDA = [
        'precio' => 'Preis',
        'competencia' => 'Wettbewerb',
        'sin_respuesta' => 'Keine Reaktion',
        'no_cualificado' => 'Nicht qualifiziert',
        'otro' => 'Sonstiges',
    ];

    /**
     * Transiciones válidas del embudo (CWE-841 — Improper Enforcement of
     * Behavioral Workflow). Solo se permite avanzar/cerrar por estos caminos.
     *
     * @var array<string, array<int, string>>
     */
    public const TRANSICIONES = [
        'nuevo' => ['contactado', 'perdido'],
        'contactado' => ['cualificado', 'perdido'],
        'cualificado' => ['propuesta', 'ganado', 'perdido'],
        'propuesta' => ['ganado', 'perdido'],
        'ganado' => [],
        'perdido' => ['nuevo'], // reabrir
    ];

    protected $fillable = [
        'tenant_id', 'nombre', 'telefono', 'email', 'mensaje',
        'fuente', 'estado', 'valor_estimado', 'motivo_perdida',
        'asignado_a', 'cliente_id', 'mensajeria_optin',
        'contactado_en', 'cerrado_en',
        'utm_source', 'utm_medium', 'utm_campaign',
    ];

    protected function casts(): array
    {
        return [
            'valor_estimado' => 'decimal:2',
            'mensajeria_optin' => 'boolean',
            'contactado_en' => 'datetime',
            'cerrado_en' => 'datetime',
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

    /** @return BelongsTo<User, $this> */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->whereNotIn('estado', ['ganado', 'perdido']);
    }

    public function scopeNuevos(Builder $query): Builder
    {
        return $query->where('estado', 'nuevo');
    }

    public function puedeTransicionarA(string $destino): bool
    {
        return in_array($destino, self::TRANSICIONES[$this->estado] ?? [], true);
    }
}
