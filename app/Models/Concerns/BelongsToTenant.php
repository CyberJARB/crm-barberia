<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * OWASP A01 - Broken Access Control / CWE-285 (IDOR prevention)
 * Global scope que filtra TODOS los queries por tenant_id automáticamente.
 * Evita que un negocio vea datos de otro negocio (multi-tenancy isolation).
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        // Aplicar scope automáticamente en todos los queries
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app()->bound('current_tenant_id')
                ? app('current_tenant_id')
                : null;

            if ($tenantId) {
                $builder->where(
                    $builder->getModel()->getTable().'.tenant_id',
                    $tenantId
                );
            }
        });

        // Al crear, inyectar tenant_id automáticamente
        static::creating(function ($model) {
            if (empty($model->tenant_id) && app()->bound('current_tenant_id')) {
                $model->tenant_id = app('current_tenant_id');
            }
        });
    }
}
