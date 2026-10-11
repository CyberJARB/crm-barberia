<?php

namespace App\Filament\Super\Widgets;

use App\Models\Tenant;
use App\Support\Planes;
use App\Support\ReporteTexto;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del SaaS para el operador: tenants, trials y MRR estimado.
 */
class SaasStatsWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $activos = Tenant::query()->where('activo', true)->count();

        $enTrial = Tenant::query()
            ->where('activo', true)
            ->where('trial_ends_at', '>', now())
            ->count();

        $nuevos30d = Tenant::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->count();

        // MRR estimado: precio del plan de cada tenant con suscripción válida
        $precios = collect(Planes::todos())->map(fn (array $p): float => (float) $p['precio']);
        $mrr = Tenant::query()
            ->where('activo', true)
            ->with('subscriptions')
            ->get()
            ->filter(fn (Tenant $t): bool => (bool) $t->subscription('default')?->valid())
            ->sum(fn (Tenant $t): float => (float) ($precios[$t->plan] ?? 0));

        return [
            Stat::make('Geschäfte aktiv', (string) $activos)
                ->description($nuevos30d.' neu in 30 Tagen')
                ->icon('heroicon-o-building-storefront')
                ->color('primary'),

            Stat::make('MRR (geschätzt)', ReporteTexto::euro($mrr))
                ->description('Zahlende Abonnements')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('In Testphase', (string) $enTrial)
                ->description('Potenzielle Conversions')
                ->icon('heroicon-o-clock')
                ->color('info'),
        ];
    }
}
