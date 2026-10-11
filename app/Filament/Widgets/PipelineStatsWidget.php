<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Lead;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del embudo de ventas — leads nuevos, valor abierto y conversión.
 * Todos los queries filtrados por tenant (OWASP A01).
 */
class PipelineStatsWidget extends StatsOverviewWidget
{
    use HasTenantContext;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', Lead::class) ?? false;
    }

    protected function getStats(): array
    {
        $tenantId = $this->currentTenantId();

        $nuevosHoy = Lead::where('tenant_id', $tenantId)
            ->where('estado', 'nuevo')
            ->whereDate('created_at', today())
            ->count();

        $valorAbierto = (float) Lead::where('tenant_id', $tenantId)
            ->whereNotIn('estado', ['ganado', 'perdido'])
            ->sum('valor_estimado');

        // Conversión de los últimos 30 días (ganados / cerrados)
        $cerrados30 = Lead::where('tenant_id', $tenantId)
            ->whereIn('estado', ['ganado', 'perdido'])
            ->where('cerrado_en', '>=', now()->subDays(30))
            ->count();

        $ganados30 = Lead::where('tenant_id', $tenantId)
            ->where('estado', 'ganado')
            ->where('cerrado_en', '>=', now()->subDays(30))
            ->count();

        $tasa = $cerrados30 > 0 ? round($ganados30 / $cerrados30 * 100) : 0;

        return [
            Stat::make('Neue Leads heute', $nuevosHoy)
                ->description('Eingegangen heute')
                ->descriptionIcon('heroicon-m-inbox-arrow-down')
                ->color($nuevosHoy > 0 ? 'success' : 'gray'),

            Stat::make('Offener Pipeline-Wert', number_format($valorAbierto, 2, ',', '.').' €')
                ->description('Summe geschätzter Wert')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),

            Stat::make('Conversion (30 Tage)', $tasa.' %')
                ->description("{$ganados30} von {$cerrados30} gewonnen")
                ->descriptionIcon($tasa >= 30 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($tasa >= 30 ? 'success' : 'warning'),
        ];
    }
}
