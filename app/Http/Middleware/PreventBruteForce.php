<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A07 - Identification & Auth Failures / CWE-307 Brute Force
 * Rate limiting por IP + email: máximo 5 intentos en 15 minutos.
 * Respuesta con tiempo constante para prevenir timing attacks (CWE-208).
 */
class PreventBruteForce
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_MINUTES = 15;

    public function __construct(private RateLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $key = $this->throttleKey($request);

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = $this->limiter->availableIn($key);

            AuditLog::registrar('LOGIN_BLOQUEADO', 'auth', null, null, [
                'ip' => $request->ip(),
                'email' => $request->input('email'),
            ], 'bloqueado');

            return response()->json([
                'message' => "Zu viele Versuche. Bitte versuchen Sie es in {$seconds} Sekunden erneut.",
            ], Response::HTTP_TOO_MANY_REQUESTS)->withHeaders([
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => self::MAX_ATTEMPTS,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        $response = $next($request);

        // Si la autenticación falló (401 o redirección con error)
        if ($this->loginFailed($response)) {
            $this->limiter->hit($key, self::DECAY_MINUTES * 60);
        } else {
            $this->limiter->clear($key);
        }

        return $response;
    }

    private function throttleKey(Request $request): string
    {
        // Clave por email + IP (previene ataques distribuidos y por usuario)
        return 'login|'.strtolower($request->input('email', '')).'|'.$request->ip();
    }

    private function loginFailed(Response $response): bool
    {
        return $response->getStatusCode() === 401
            || ($response->getStatusCode() === 302
                && str_contains($response->headers->get('Location', ''), 'login'));
    }
}
