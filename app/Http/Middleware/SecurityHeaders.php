<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A05 - Misconfiguration / UA2 T03 - Defense in Depth
 * Aplica cabeceras HTTP de seguridad en todas las respuestas.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Previene clickjacking (CWE-1021)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Previene MIME sniffing (CWE-430)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Desactiva el auditor XSS legacy — la recomendación moderna de OWASP es "0" (CWE-79)
        $response->headers->set('X-XSS-Protection', '0');

        // Fuerza HTTPS (HSTS) - solo en producción
        if (app()->isProduction()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains; preload'
            );
        }

        // Limita información del referrer
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Deshabilita funciones de navegador no necesarias (Permissions-Policy / antiguo Feature-Policy)
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=(), magnetometer=(), gyroscope=(), accelerometer=(), midi=(), fullscreen=(self)'
        );

        // Aislamiento de contexto de navegación — mitiga XS-Leaks/Spectre (OWASP A05)
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        // Impide que otros orígenes embeban nuestros recursos (CWE-1275)
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');

        // Bloquea políticas cross-domain de Flash/PDF legacy (OWASP Secure Headers Project)
        $response->headers->set('X-Permitted-Cross-Domain-Policies', 'none');

        // Content Security Policy - previene XSS (CWE-79)
        if (config('security.csp.enabled', true)) {
            $response->headers->set('Content-Security-Policy', $this->buildCsp());
        }

        // Elimina firma del servidor
        $response->headers->remove('X-Powered-By');
        $response->headers->remove('Server');

        // X-Request-ID — trazabilidad de peticiones para respuesta a incidentes
        // (ISO 27001 A.16.1 / NIST CSF Respond)
        if (! $response->headers->has('X-Request-ID')) {
            $requestId = $request->header('X-Request-ID') ?? Str::uuid()->toString();
            $response->headers->set('X-Request-ID', $requestId);
            // Propagar al log de Laravel para correlacionar peticiones
            Log::withContext(['request_id' => $requestId]);
        }

        return $response;
    }

    /** Construye la CSP desde config/security.php (centralizado, OWASP A05) */
    private function buildCsp(): string
    {
        $extraScript = implode(' ', config('security.csp.extra_script_src', []));
        $extraConnect = implode(' ', config('security.csp.extra_connect_src', []));
        $extraFrame = implode(' ', config('security.csp.extra_frame_src', []));

        // 'unsafe-inline'/'unsafe-eval' requeridos por Livewire/Alpine salvo build CSP estricta
        $scriptSrc = config('security.csp.strict_scripts', false)
            ? "script-src 'self' {$extraScript}"
            : "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$extraScript}";

        $directivas = [
            "default-src 'self'",
            trim($scriptSrc),
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self' data:",
            trim("connect-src 'self' ws: wss: {$extraConnect}"),  // WebSockets para Livewire
            trim("frame-src 'self' {$extraFrame}"),               // reCAPTCHA iframe
            "object-src 'none'",          // Bloquea plugins embebidos (CWE-79)
            "frame-ancestors 'self'",
            "form-action 'self'",
            "base-uri 'self'",
        ];

        // Reescribe peticiones HTTP a HTTPS en producción (CWE-319)
        if (app()->isProduction()) {
            $directivas[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directivas);
    }
}
