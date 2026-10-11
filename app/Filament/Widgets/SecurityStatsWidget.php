<?php

namespace App\Filament\Widgets;

use App\Models\AuditLog;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * OWASP A09 / NIST CSF Detect
 * Panel rápido de estado de seguridad — solo visible para propietario/admin.
 */
class SecurityStatsWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->esAdmin();
    }

    protected function getStats(): array
    {
        $tenantId = auth()->user()?->tenant_id;

        $loginsFallidos = AuditLog::where('tenant_id', $tenantId)
            ->where('accion', 'CUENTA_BLOQUEADA')
            ->whereDate('created_at', today())
            ->count();

        $cuentasBloqueadas = User::where('tenant_id', $tenantId)
            ->where('bloqueado_hasta', '>', now())
            ->count();

        $accionesHoy = AuditLog::where('tenant_id', $tenantId)
            ->whereDate('created_at', today())
            ->count();

        $eliminacionesHoy = AuditLog::where('tenant_id', $tenantId)
            ->where('accion', 'DELETE')
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Gesperrte Konten', $cuentasBloqueadas)
                ->description('Aktuell gesperrt')
                ->descriptionIcon('heroicon-m-lock-closed')
                ->color($cuentasBloqueadas > 0 ? 'danger' : 'success'),

            Stat::make('Sperrungen heute', $loginsFallidos)
                ->description('Anmeldeversuche, die zur Sperrung führten')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($loginsFallidos > 2 ? 'danger' : ($loginsFallidos > 0 ? 'warning' : 'success')),

            Stat::make('Auditierte Aktionen heute', $accionesHoy)
                ->description("{$eliminacionesHoy} Löschungen")
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color($eliminacionesHoy > 20 ? 'warning' : 'info'),
        ];
    }
}
