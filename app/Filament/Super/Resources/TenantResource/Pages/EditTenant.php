<?php

namespace App\Filament\Super\Resources\TenantResource\Pages;

use App\Filament\Super\Resources\TenantResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\EditRecord;

class EditTenant extends EditRecord
{
    protected static string $resource = TenantResource::class;

    /** Cambios de Zulassung siempre auditados (OWASP A09). */
    protected function afterSave(): void
    {
        $tenant = $this->getRecord();

        $cambios = $tenant->getChanges();
        unset($cambios['updated_at']);

        if ($cambios !== []) {
            AuditLog::registrar('SAAS_TENANT_EDITADO', 'Tenant', $tenant->getKey(), null, $cambios);
        }
    }
}
