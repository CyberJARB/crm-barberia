<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BeliebtesteLeistungenWidget;
use App\Filament\Widgets\CitasEstadoChart;
use App\Filament\Widgets\IngresosChart;
use App\Filament\Widgets\OnboardingWidget;
use App\Filament\Widgets\ProximasCitasWidget;
use App\Filament\Widgets\RecentSecurityAlertsWidget;
use App\Filament\Widgets\ResumenWidget;
use App\Filament\Widgets\SecurityStatsWidget;
use App\Filament\Widgets\UmsatzProMitarbeiterWidget;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Dashboard principal — visión del negocio en el primer pantallazo:
 * KPIs, ingresos de 30 días, estado de citas y agenda de hoy.
 */
class Dashboard extends BaseDashboard
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $title = 'Dashboard';

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return 3;
    }

    public function getWidgets(): array
    {
        return [
            // Checklist de arranque — solo visible mientras falten pasos
            OnboardingWidget::class,
            ResumenWidget::class,
            IngresosChart::class,
            CitasEstadoChart::class,
            UmsatzProMitarbeiterWidget::class,
            BeliebtesteLeistungenWidget::class,
            ProximasCitasWidget::class,
            // Widgets de seguridad existentes (solo admin, OWASP A09)
            SecurityStatsWidget::class,
            RecentSecurityAlertsWidget::class,
        ];
    }
}
