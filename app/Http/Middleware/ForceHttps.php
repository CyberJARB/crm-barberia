<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A02 / CWE-319 — Cleartext Transmission of Sensitive Information
 * Fuerza HTTPS en producción. Sin HTTPS, las credenciales y tokens
 * viajan en texto claro y son interceptables (Man-in-the-Middle).
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->isLocal() && ! $request->secure()) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
