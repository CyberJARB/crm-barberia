<?php

namespace App\Filament\Super\Widgets;

use App\Models\AuditLog;
use App\Models\Cita;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

/**
 * Salud operativa del SaaS: cola, fallos de envío y actividad global.
 * NIST CSF Detect — el operador ve los problemas antes que los clientes.
 */
class SistemaStatusWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '60s';

    protected function getStats(): array
    {
        $jobsFallidos = (int) DB::table('failed_jobs')->count();

        $jobsPendientes = (int) DB::table('jobs')->count();

        $falloEnvios7d = AuditLog::query()
            ->whereIn('accion', ['EMAIL_FALLO', 'MENSAJE_FALLO'])
            ->where('created_at', '>=', now()->subDays(7))
            ->count();

        $citasHoy = Cita::withoutGlobalScopes()
            ->whereDate('created_at', today())
            ->count();

        return [
            Stat::make('Fehlgeschlagene Jobs', (string) $jobsFallidos)
                ->description($jobsFallidos > 0 ? '⚠️ php artisan queue:retry all' : 'Alles in Ordnung')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($jobsFallidos > 0 ? 'danger' : 'success'),

            Stat::make('Jobs in der Warteschlange', (string) $jobsPendientes)
                ->description($jobsPendientes > 50 ? 'Läuft der Worker? (queue:work)' : 'Normal')
                ->icon('heroicon-o-queue-list')
                ->color($jobsPendientes > 50 ? 'warning' : 'gray'),

            Stat::make('Versandfehler (7 Tage)', (string) $falloEnvios7d)
                ->description('E-Mail + WhatsApp/SMS')
                ->icon('heroicon-o-envelope')
                ->color($falloEnvios7d > 0 ? 'warning' : 'success'),

            Stat::make('Neue Termine heute (alle)', (string) $citasHoy)
                ->description('Aktivität der Plattform')
                ->icon('heroicon-o-calendar-days')
                ->color('info'),
        ];
    }
}
