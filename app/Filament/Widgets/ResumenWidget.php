<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs principales del negocio — citas, ingresos y clientes.
 * Todos los queries filtrados por tenant (OWASP A01).
 */
class ResumenWidget extends StatsOverviewWidget
{
    use HasTenantContext;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $tenantId = $this->currentTenantId();

        // ── Citas hoy vs ayer ────────────────────────────────────────────
        $citasHoy = Cita::where('tenant_id', $tenantId)
            ->whereDate('fecha', today())
            ->count();

        $citasAyer = Cita::where('tenant_id', $tenantId)
            ->whereDate('fecha', today()->subDay())
            ->count();

        // ── Ingresos del mes vs mes anterior ─────────────────────────────
        $ingresosMes = (float) Pago::where('tenant_id', $tenantId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->sum('monto');

        $mesAnterior = now()->subMonthNoOverflow();

        $ingresosMesAnterior = (float) Pago::where('tenant_id', $tenantId)
            ->whereYear('created_at', $mesAnterior->year)
            ->whereMonth('created_at', $mesAnterior->month)
            ->sum('monto');

        // ── Clientes activos vs cierre del mes anterior ──────────────────
        $clientesActivos = Cliente::where('tenant_id', $tenantId)
            ->where('activo', true)
            ->count();

        $activosMesAnterior = Cliente::where('tenant_id', $tenantId)
            ->where('activo', true)
            ->where('created_at', '<', now()->startOfMonth())
            ->count();

        $difActivos = $clientesActivos - $activosMesAnterior;

        // ── Clientes nuevos del mes ──────────────────────────────────────
        $clientesNuevos = Cliente::where('tenant_id', $tenantId)
            ->whereYear('created_at', now()->year)
            ->whereMonth('created_at', now()->month)
            ->count();

        // ── Tasa de no-shows del mes (OWASP A01 — filtro explícito por tenant)
        $citasMes = Cita::where('tenant_id', $tenantId)
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->count();

        $noShowMes = Cita::where('tenant_id', $tenantId)
            ->where('estado', 'no_show')
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->count();

        $tasaNoShow = $citasMes > 0 ? (int) round($noShowMes / $citasMes * 100) : 0;

        return [
            Stat::make('Termine heute', $citasHoy)
                ->description("{$citasAyer} gestern")
                ->descriptionIcon($citasHoy >= $citasAyer
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->color($citasHoy >= $citasAyer ? 'success' : 'danger'),

            Stat::make('Einnahmen (Monat)', number_format($ingresosMes, 2, ',', '.').' €')
                ->description(number_format($ingresosMesAnterior, 2, ',', '.').' € im Vormonat')
                ->descriptionIcon($ingresosMes >= $ingresosMesAnterior
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->color($ingresosMes >= $ingresosMesAnterior ? 'success' : 'danger'),

            Stat::make('Aktive Kunden', $clientesActivos)
                ->description(($difActivos >= 0 ? '+' : '').$difActivos.' seit Vormonat')
                ->descriptionIcon($difActivos >= 0
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->color($difActivos >= 0 ? 'success' : 'danger'),

            Stat::make('Neue Kunden (Monat)', $clientesNuevos)
                ->description('Im laufenden Monat registriert')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),

            Stat::make('Nicht erschienen (Monat)', "{$noShowMes} ({$tasaNoShow}%)")
                ->description($noShowMes > 0
                    ? 'Verlorener Umsatz — mit Erinnerungen reduzierbar'
                    : 'Keine No-Shows — top!')
                ->descriptionIcon($tasaNoShow > 0
                    ? 'heroicon-m-exclamation-triangle'
                    : 'heroicon-m-check-circle')
                ->color($tasaNoShow > 0 ? 'danger' : 'success'),
        ];
    }
}
