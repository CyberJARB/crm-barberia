<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica la API pública de reservas mediante API key por tenant.
 *
 * OWASP A01/A07 — la clave viaja en el header X-API-Key, se compara por
 * hash SHA-256 y al resolver el tenant se vincula 'current_tenant_id',
 * con lo que el global scope BelongsToTenant aísla TODOS los queries
 * del request (CWE-285 / IDOR prevention).
 */
class AutenticarTenantApi
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = (string) $request->header('X-API-Key', '');

        if ($key === '') {
            return response()->json(['message' => 'API key requerida.'], 401);
        }

        $tenant = Tenant::where('api_key_hash', hash('sha256', $key))
            ->where('activo', true)
            ->first();

        if (! $tenant) {
            return response()->json(['message' => 'API key inválida.'], 401);
        }

        // OWASP A04 — el acceso API externo es feature del plan Premium
        if (! $tenant->permite('api')) {
            return response()->json([
                'message' => 'El plan actual no incluye acceso API.',
                'code' => 'PLAN_ERFORDERLICH',
            ], 403);
        }

        app()->instance('current_tenant_id', $tenant->id);
        $request->attributes->set('tenant', $tenant);

        return $next($request);
    }
}
