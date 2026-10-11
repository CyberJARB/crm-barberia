<?php

namespace App\Models;

use App\Support\SecurityLogger;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string $password
 * @property int|null $tenant_id
 * @property string $rol
 * @property bool $activo
 * @property int $intentos_fallidos
 * @property Carbon|null $bloqueado_hasta
 * @property Carbon|null $ultimo_login
 * @property string|null $ultimo_login_ip
 * @property bool $debe_cambiar_password
 * @property Carbon|null $password_changed_at
 * @property-read Tenant|null $tenant
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use HasFactory, Notifiable;
    use InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;

    protected $fillable = [
        'name', 'email', 'password', 'tenant_id', 'rol', 'activo',
        'intentos_fallidos', 'bloqueado_hasta', 'ultimo_login',
        'ultimo_login_ip', 'debe_cambiar_password', 'password_changed_at',
        'app_authentication_secret', 'app_authentication_recovery_codes',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_secret', 'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'ultimo_login' => 'datetime',
            'password_changed_at' => 'datetime',
            'debe_cambiar_password' => 'boolean',
        ];
    }

    /** @return BelongsTo<Tenant, $this> */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** @return HasOne<Personal, $this> */
    public function personal(): HasOne
    {
        return $this->hasOne(Personal::class);
    }

    /** @return HasMany<PasswordHistory, $this> */
    public function passwordHistories(): HasMany
    {
        return $this->hasMany(PasswordHistory::class);
    }

    /** @return HasMany<Lead, $this> */
    public function leadsAsignados(): HasMany
    {
        return $this->hasMany(Lead::class, 'asignado_a');
    }

    /**
     * CWE-521 / NIST SP 800-63B — Guarda la contraseña actual en el historial
     * antes de que sea sobreescrita. Llama esto ANTES de cambiar el password.
     */
    public function guardarEnHistorial(): void
    {
        if (! $this->password) {
            return;
        }

        PasswordHistory::create([
            'user_id' => $this->id,
            'password' => $this->password,
        ]);

        // Mantener solo los últimos 10 registros (MySQL no soporta OFFSET en DELETE)
        $total = PasswordHistory::where('user_id', $this->id)->count();
        if ($total > 10) {
            $idsAEliminar = PasswordHistory::where('user_id', $this->id)
                ->orderBy('created_at')
                ->limit($total - 10)
                ->pluck('id');

            PasswordHistory::whereIn('id', $idsAEliminar)->delete();
        }
    }

    // === OWASP A07 / CWE-307 — Account lockout (valores centralizados en config/security.php) ===

    private static function maxIntentos(): int
    {
        return (int) config('security.lockout.max_intentos', 5);
    }

    private static function minutosBloqueo(): int
    {
        return (int) config('security.lockout.minutos_bloqueo', 15);
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null
            && $this->bloqueado_hasta->isFuture();
    }

    public function minutosParaDesbloqueo(): int
    {
        if (! $this->estaBloqueado()) {
            return 0;
        }

        return (int) ceil(now()->diffInSeconds($this->bloqueado_hasta) / 60);
    }

    public function registrarIntentoFallido(): void
    {
        $nuevos = $this->intentos_fallidos + 1;

        $this->update([
            'intentos_fallidos' => $nuevos,
            'bloqueado_hasta' => $nuevos >= self::maxIntentos()
                ? now()->addMinutes(self::minutosBloqueo())
                : null,
        ]);

        if ($nuevos >= self::maxIntentos()) {
            SecurityLogger::loginBloqueado($this->email, request()->ip() ?? 'unknown');
            AuditLog::registrar('CUENTA_BLOQUEADA', 'User', $this->id, null, [
                'intentos' => $nuevos,
                'ip' => request()->ip(),
            ], 'bloqueado');
        }
    }

    public function registrarLoginExitoso(): void
    {
        $this->update([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
            'ultimo_login' => now(),
            'ultimo_login_ip' => request()->ip(),
        ]);

        SecurityLogger::loginExitoso($this->email, request()->ip() ?? 'unknown');
        AuditLog::registrar('LOGIN_SUCCESS', 'User', $this->id, null, null, 'ok');
    }

    // === Filament access control (OWASP A01) ===

    /**
     * Separación estricta de paneles: el superadmin (operador del SaaS,
     * sin tenant) solo entra a /super; los usuarios de negocio solo a /admin.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->activo) {
            return false;
        }

        if ($panel->getId() === 'super') {
            return $this->esSuperAdmin();
        }

        return ! $this->esSuperAdmin() && $this->tenant_id !== null;
    }

    // === Roles (OWASP A01 / RBAC) ===

    public function esSuperAdmin(): bool
    {
        return $this->rol === 'superadmin' && $this->tenant_id === null;
    }

    public function esPropietario(): bool
    {
        return $this->rol === 'propietario';
    }

    public function esAdmin(): bool
    {
        return in_array($this->rol, ['propietario', 'admin']);
    }

    public function esRecepcionista(): bool
    {
        return in_array($this->rol, ['propietario', 'admin', 'recepcionista']);
    }
}
