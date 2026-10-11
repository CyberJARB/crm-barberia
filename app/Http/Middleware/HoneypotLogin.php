<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Support\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CWE-307 / OWASP A07 — Anti-bot honeypot en formularios de login.
 * Un campo oculto "website" (que ningún humano llena) si viene con valor
 * indica un bot/crawler → bloquear silenciosamente con respuesta falsa de éxito.
 */
class HoneypotLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo aplica a POST de login
        if (! $request->isMethod('POST') || ! $request->is('admin/login')) {
            return $next($request);
        }

        // Si el campo honeypot tiene valor → es un bot
        $honeypot = $request->input('_hp_website', '');

        if (! empty($honeypot)) {
            SecurityLogger::loginFallido(
                $request->input('email', 'bot'),
                $request->ip() ?? 'unknown'
            );

            AuditLog::registrar('BOT_DETECTADO', 'Auth', null, null, [
                'ip' => $request->ip(),
                'honeypot' => substr($honeypot, 0, 100),
            ], 'bloqueado');

            // Respuesta falsa de 200 para no revelar que fue detectado
            return response()->json(['message' => 'OK'], 200);
        }

        return $next($request);
    }
}
