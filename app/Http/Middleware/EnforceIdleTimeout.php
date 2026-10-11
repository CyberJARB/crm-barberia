<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zero Trust / OWASP A07 / NIST 800-63B — cierre de sesión por inactividad.
 *
 * Independiente del lifetime absoluto: si pasan más de N minutos sin
 * actividad, la sesión se invalida y se exige re-autenticación. Reduce la
 * ventana de secuestro de sesión en equipos compartidos/desatendidos.
 *
 * `security.session.idle_timeout` en minutos; 0 = desactivado.
 */
class EnforceIdleTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $minutes = (int) config('security.session.idle_timeout', 0);

        if ($minutes > 0 && Auth::check()) {
            $last = $request->session()->get('last_activity');
            $now = now()->timestamp;

            if ($last !== null && ($now - (int) $last) > $minutes * 60) {
                Log::channel('security')->info('SESSION_IDLE_TIMEOUT', [
                    'user_id' => Auth::id(),
                    'inactivo_min' => intdiv($now - (int) $last, 60),
                ]);

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Como invitado, el siguiente request será redirigido al login
                // del panel correspondiente por el middleware Authenticate.
                return redirect()->to($request->fullUrl());
            }

            $request->session()->put('last_activity', $now);
        }

        return $next($request);
    }
}
