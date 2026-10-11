<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A07 / CWE-307 — Brute Force Protection
 * Verifica si la cuenta ya está bloqueada ANTES de intentar autenticación.
 * El bloqueo se persiste en BD — sobrevive reinicios del servidor.
 */
class CheckAccountLockout
{
    public function handle(Request $request, Closure $next): Response
    {
        $email = $request->input('email');

        if ($email) {
            $user = User::where('email', $email)->first();

            if ($user && $user->estaBloqueado()) {
                $minutos = $user->minutosParaDesbloqueo();

                SecurityLogger::loginBloqueado($email, $request->ip());

                // Respuesta genérica — no revelar estado interno (CWE-200)
                return response()->json([
                    'message' => "Konto vorübergehend gesperrt. Bitte versuchen Sie es in {$minutos} Minuten erneut.",
                ], Response::HTTP_TOO_MANY_REQUESTS);
            }
        }

        return $next($request);
    }
}
