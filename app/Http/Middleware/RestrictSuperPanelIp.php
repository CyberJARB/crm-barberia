<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Zero Trust / OWASP A01 — restringe el panel del operador (/super) a una
 * allowlist de IPs. Es la cuenta más sensible del sistema (gestiona TODOS
 * los tenants), por lo que limitar el origen reduce drásticamente la
 * superficie de ataque.
 *
 * Soporta IPs exactas y rangos CIDR (ej. 203.0.113.0/24) vía Symfony IpUtils.
 * Si la allowlist está vacía (no configurada), NO restringe — así local,
 * testing y despliegues sin IP fija siguen funcionando.
 */
class RestrictSuperPanelIp
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowlist = config('security.super_ip_allowlist', []);

        if (empty($allowlist)) {
            return $next($request);
        }

        if (! IpUtils::checkIp((string) $request->ip(), $allowlist)) {
            Log::channel('security')->warning('SUPER_IP_BLOCKED', [
                'ip' => $request->ip(),
                'path' => $request->path(),
            ]);

            abort(403);
        }

        return $next($request);
    }
}
