<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * OWASP A01 - Broken Access Control / UA2 T02 - RBAC
 * Garantiza que cada usuario solo opera dentro de su tenant.
 * Principio: mínimo privilegio + separación de datos (multi-tenancy).
 */
class EnsureTenantScope
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->tenant_id) {
            // Compartir tenant_id en el contexto de la request
            app()->instance('current_tenant_id', $user->tenant_id);
        }

        return $next($request);
    }
}
