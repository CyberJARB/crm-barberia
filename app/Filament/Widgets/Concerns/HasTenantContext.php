<?php

namespace App\Filament\Widgets\Concerns;

/**
 * OWASP A01 — Aislamiento de tenant en widgets.
 *
 * El global scope BelongsToTenant depende de que el middleware
 * EnsureTenantScope haya inyectado `current_tenant_id` en el contenedor.
 * En peticiones Livewire (polling / lazy-load de widgets) ese middleware
 * puede no ejecutarse, por lo que los widgets filtran SIEMPRE de forma
 * explícita por el tenant del usuario autenticado (defensa en profundidad).
 */
trait HasTenantContext
{
    protected function currentTenantId(): ?int
    {
        if (app()->bound('current_tenant_id')) {
            return (int) app('current_tenant_id');
        }

        return auth()->user()?->tenant_id;
    }
}
