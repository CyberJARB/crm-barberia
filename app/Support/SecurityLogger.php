<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * ISO 27001 A.12.4 / NIST CSF Detect / UA1 T02
 * Centraliza el logging de eventos de seguridad.
 * Los logs de seguridad se escriben en storage/logs/security.log
 * con retención de 90 días (GDPR Art. 30 / NIS2).
 */
class SecurityLogger
{
    public static function loginExitoso(string $email, string $ip): void
    {
        self::log('info', 'LOGIN_SUCCESS', [
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    public static function loginFallido(string $email, string $ip, string $razon = ''): void
    {
        self::log('warning', 'LOGIN_FAILED', [
            'email' => $email,
            'ip' => $ip,
            'razon' => $razon,
        ]);
    }

    public static function loginBloqueado(string $email, string $ip): void
    {
        self::log('alert', 'LOGIN_BLOCKED', [
            'email' => $email,
            'ip' => $ip,
        ]);
    }

    public static function accesoNoAutorizado(string $recurso, ?int $userId, string $ip): void
    {
        self::log('warning', 'UNAUTHORIZED_ACCESS', [
            'recurso' => $recurso,
            'user_id' => $userId,
            'ip' => $ip,
        ]);
    }

    public static function sesionExpirada(int $userId): void
    {
        self::log('info', 'SESSION_EXPIRED', ['user_id' => $userId]);
    }

    private static function log(string $level, string $evento, array $contexto): void
    {
        Log::channel('security')->{$level}($evento, array_merge($contexto, [
            'timestamp' => now()->toIso8601String(),
            'app' => config('app.name'),
        ]));
    }
}
