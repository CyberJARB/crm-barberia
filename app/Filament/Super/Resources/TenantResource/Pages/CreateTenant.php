<?php

namespace App\Filament\Super\Resources\TenantResource\Pages;

use App\Filament\Super\Resources\TenantResource;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Alta manual de un negocio (venta directa de CyberJARB): crea el tenant
 * con trial de 14 días + la cuenta del propietario con contraseña temporal.
 */
class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    private string $inhaberNombre = '';

    private string $inhaberEmail = '';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->inhaberNombre = (string) ($data['inhaber_nombre'] ?? '');
        $this->inhaberEmail = strtolower(trim((string) ($data['inhaber_email'] ?? '')));
        unset($data['inhaber_nombre'], $data['inhaber_email']);

        // Defaults de alta: igual que el registro self-service
        $data['slug'] = $this->generarSlug((string) $data['nombre']);
        $data['pais'] = $data['pais'] ?? 'DE';
        $data['moneda'] = $data['moneda'] ?? 'EUR';
        $data['zona_horaria'] = $data['zona_horaria'] ?? 'Europe/Berlin';
        $data['trial_ends_at'] = $data['trial_ends_at'] ?? now()->addDays(14);
        $data['activo'] = $data['activo'] ?? true;

        return $data;
    }

    protected function afterCreate(): void
    {
        /** @var Tenant $tenant */
        $tenant = $this->getRecord();

        // CWE-521 — contraseña temporal fuerte; debe cambiarla al entrar
        $temporal = Str::password(16);

        $user = User::create([
            'name' => strip_tags(trim($this->inhaberNombre)),
            'email' => $this->inhaberEmail,
            'password' => Hash::make($temporal),
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
            'activo' => true,
            'debe_cambiar_password' => true,
        ]);

        AuditLog::registrar('SAAS_TENANT_CREADO', 'Tenant', $tenant->id, null, [
            'inhaber' => $user->email,
            'plan' => $tenant->plan,
        ]);

        Notification::make()
            ->title('Geschäft angelegt — Zugangsdaten (einmalige Anzeige!)')
            ->body("Login: /admin · {$user->email} · Passwort: {$temporal}")
            ->persistent()
            ->warning()->send();
    }

    private function generarSlug(string $nombre): string
    {
        $base = Str::slug($nombre);
        $slug = $base;
        $i = 2;

        while (Tenant::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
