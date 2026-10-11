<?php

namespace App\Filament\Super\Widgets;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Tenant;
use App\Support\ReporteTexto;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Uso real de un negocio (vista de detalle del operador): actividad,
 * ingresos y adopción — la base para conversaciones de upgrade/churn.
 */
class TenantUsoWidget extends StatsOverviewWidget
{
    public ?Tenant $record = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenant = $this->record;

        if ($tenant === null) {
            return [];
        }

        $tid = $tenant->id;

        $citas30d = Cita::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        $ingresos30d = (float) Pago::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->where('created_at', '>=', now()->subDays(30))
            ->sum('monto');

        $clientes = Cliente::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->count();

        $ultimoLogin = $tenant->users()
            ->whereNotNull('ultimo_login')
            ->max('ultimo_login');

        return [
            Stat::make('Termine (30 Tage)', (string) $citas30d)
                ->description($citas30d === 0 ? '⚠️ Inaktiv — Churn-Risiko' : 'Aktive Nutzung')
                ->icon('heroicon-o-calendar-days')
                ->color($citas30d === 0 ? 'danger' : 'primary'),

            Stat::make('Umsatz des Geschäfts (30 Tage)', ReporteTexto::euro($ingresos30d))
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Kunden gesamt', (string) $clientes)
                ->icon('heroicon-o-users')
                ->color('info'),

            Stat::make('Letzter Login', $ultimoLogin !== null
                    ? Carbon::parse($ultimoLogin)->format('d.m.Y H:i')
                    : 'Nie')
                ->icon('heroicon-o-clock')
                ->color($ultimoLogin === null ? 'danger' : 'gray'),
        ];
    }
}
