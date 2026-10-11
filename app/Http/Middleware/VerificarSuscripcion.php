<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloquea el acceso al panel cuando el trial de 14 días expiró y el tenant
 * no tiene suscripción activa, redirigiendo a /admin/suscripcion.
 *
 * Solo actúa en producción: el chequeo se hace aquí dentro (y no en
 * bootstrap/app.php) porque app()->isProduction() lee config('app.env'),
 * que es seguro con config:cache; env() en bootstrap devolvería null
 * con la config cacheada (CWE-1188).
 */
class VerificarSuscripcion
{
    public function handle(Request $request, Closure $next): Response
    {
        // Solo en producción — no bloquear desarrollo ni tests
        if (! app()->isProduction()) {
            return $next($request);
        }

        $user = $request->user();

        // Invitados (login, registro, precios...) no se evalúan aquí
        if ($user === null || $user->tenant === null) {
            return $next($request);
        }

        if ($this->rutaExcluida($request)) {
            return $next($request);
        }

        $tenant = $user->tenant;

        // Acceso permitido: trial vigente (columna trial_ends_at en tenants)
        if ($tenant->onGenericTrial()) {
            return $next($request);
        }

        // Acceso permitido: suscripción válida (activa, en trial de Stripe
        // o cancelada pero dentro del período de gracia ya pagado)
        if ($tenant->subscription('default')?->valid()) {
            return $next($request);
        }

        return redirect()
            ->route('filament.admin.pages.suscripcion')
            ->with(
                'suscripcion_requerida',
                'Ihre 14-tägige Testphase ist abgelaufen. Bitte wählen Sie einen Plan, um weiterzuarbeiten.'
            );
    }

    /**
     * Rutas que NUNCA se bloquean: auth (login/logout), registro, precios,
     * todo lo de Stripe/Cashier (webhook, checkout, retorno), healthcheck,
     * la propia página de suscripción y las peticiones internas de Livewire
     * (necesarias para que la página de suscripción funcione).
     */
    private function rutaExcluida(Request $request): bool
    {
        $nombre = (string) $request->route()?->getName();

        // Peticiones internas de Livewire (update/upload) — el GET de la
        // página destino ya queda bloqueado por este mismo middleware
        if (str_contains($nombre, 'livewire')) {
            return true;
        }

        return $request->routeIs(
            'registro',
            'precios',
            'stripe.*',                          // checkout / retorno
            'cashier.*',                         // webhook (firma CWE-345) y payment
            'filament.admin.auth.*',             // login / logout / profile
            'filament.admin.pages.suscripcion',  // destino del redirect (evita bucle)
        ) || $request->is('up', 'stripe/*');
    }
}
