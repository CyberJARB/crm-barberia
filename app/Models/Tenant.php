<?php

namespace App\Models;

use App\Support\Planes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Laravel\Cashier\Billable;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * @property int $id
 * @property string $nombre
 * @property string $tipo
 * @property string $slug
 * @property string|null $telefono
 * @property string|null $email
 * @property string|null $direccion
 * @property string|null $ciudad
 * @property string $pais
 * @property string|null $moneda
 * @property string|null $zona_horaria
 * @property string|null $color_primario
 * @property string $plan
 * @property bool $activo
 * @property Carbon|null $trial_ends_at
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $smtp_encryption
 * @property string|null $mail_from_address
 * @property string|null $mail_from_name
 * @property bool $email_confirmacion_activo
 * @property bool $email_recordatorio_activo
 * @property bool $email_followup_activo
 * @property int $email_followup_horas
 * @property bool $email_geburtstag_activo
 * @property bool $resumen_activo
 * @property string $resumen_frecuencia
 * @property bool $mensajeria_activa
 * @property string|null $api_key_hash
 * @property string|null $stripe_id
 * @property string|null $pm_type
 * @property string|null $pm_last_four
 */
class Tenant extends Model
{
    use Billable, HasFactory;

    /** Cadencias del update del negocio (clave BD => etiqueta UI). */
    public const RESUMEN_FRECUENCIAS = [
        'manana' => 'Jeden Morgen (7:00 Uhr)',
        '3h' => 'Alle 3 Stunden (8–20 Uhr)',
        '6h' => 'Alle 6 Stunden (8/14/20 Uhr)',
        '12h' => 'Zweimal täglich (8 und 20 Uhr)',
    ];

    /** A qué horas (0-23) toca enviar cada cadencia. */
    public const RESUMEN_HORAS = [
        'manana' => [7],
        '3h' => [8, 11, 14, 17, 20],
        '6h' => [8, 14, 20],
        '12h' => [8, 20],
    ];

    protected $fillable = [
        'nombre', 'tipo', 'slug', 'telefono', 'email', 'direccion',
        'ciudad', 'pais', 'moneda', 'zona_horaria', 'color_primario',
        'plan', 'activo', 'trial_ends_at',
        'smtp_host', 'smtp_port', 'smtp_username', 'smtp_password',
        'smtp_encryption', 'mail_from_address', 'mail_from_name',
        'email_confirmacion_activo', 'email_recordatorio_activo',
        'email_followup_activo', 'email_followup_horas',
        'email_geburtstag_activo',
        'resumen_activo', 'resumen_frecuencia',
        'mensajeria_activa',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'trial_ends_at' => 'datetime',
            'smtp_port' => 'integer',
            'email_confirmacion_activo' => 'boolean',
            'email_recordatorio_activo' => 'boolean',
            'email_followup_activo' => 'boolean',
            'email_followup_horas' => 'integer',
            'email_geburtstag_activo' => 'boolean',
            'resumen_activo' => 'boolean',
            'mensajeria_activa' => 'boolean',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    // === Límites por plan (gating server-side, OWASP A04) ===

    /**
     * Plan que aplica AHORA: durante el trial de 14 días se prueba el
     * producto completo (= premium) para maximizar la conversión;
     * después manda el plan contratado.
     */
    public function planEfectivo(): string
    {
        return $this->onGenericTrial() ? 'premium' : ($this->plan ?: 'basico');
    }

    /** ¿El plan actual incluye esta feature? (whatsapp | berichte | api) */
    public function permite(string $feature): bool
    {
        return (bool) (Planes::limites($this->planEfectivo())[$feature] ?? false);
    }

    /** Máximo de empleados del plan (null = ilimitado). */
    public function limitePersonal(): ?int
    {
        return Planes::limites($this->planEfectivo())['max_personal'];
    }

    public function puedeCrearPersonal(): bool
    {
        $limite = $this->limitePersonal();

        if ($limite === null) {
            return true;
        }

        return Personal::withoutGlobalScopes()
            ->where('tenant_id', $this->id)
            ->count() < $limite;
    }

    /**
     * Estado comercial del tenant para el panel del operador:
     * suspendido | activa (suscripción válida) | trial | vencido.
     */
    public function estadoSuscripcion(): string
    {
        return match (true) {
            ! $this->activo => 'suspendido',
            (bool) $this->subscription('default')?->valid() => 'activa',
            $this->onGenericTrial() => 'trial',
            default => 'vencido',
        };
    }

    /** @return HasMany<Cliente, $this> */
    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    /** @return HasMany<Servicio, $this> */
    public function servicios(): HasMany
    {
        return $this->hasMany(Servicio::class);
    }

    /** @return HasMany<Personal, $this> */
    public function personal(): HasMany
    {
        return $this->hasMany(Personal::class);
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

    // === Email / SMTP per tenant ===

    public function mailFromAddress(): string
    {
        return $this->mail_from_address ?: $this->email ?: config('mail.from.address', '');
    }

    public function mailFromName(): string
    {
        return $this->mail_from_name ?: $this->nombre;
    }

    /** Builds a Mailer using this tenant's SMTP config (DSGVO Art.6 1b). */
    public function crearMailer(): Mailer
    {
        $host = $this->smtp_host ?: config('mail.mailers.smtp.host', 'localhost');
        $port = $this->smtp_port ?: 587;
        $ssl = ($this->smtp_encryption ?? 'tls') === 'ssl';

        $transport = new EsmtpTransport(
            $host, $port, $ssl
        );

        if ($this->smtp_username) {
            $transport->setUsername($this->smtp_username);
            $password = $this->smtp_password
                ? Crypt::decryptString($this->smtp_password)
                : '';
            $transport->setPassword($password);
        }

        // El Mailer de Laravel espera el TransportInterface directamente;
        // envolverlo en Symfony\Mailer\Mailer provocaba un TypeError en runtime
        return new Mailer(
            'tenant_'.$this->id,
            app('view'),
            $transport,
            app('events'),
        );
    }

    public function onPlan(string $plan): bool
    {
        return $this->plan === $plan;
    }

    public function puedeUsarWhatsApp(): bool
    {
        return in_array($this->plan, ['profesional', 'premium']);
    }
}
