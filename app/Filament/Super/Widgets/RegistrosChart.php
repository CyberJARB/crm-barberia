<?php

namespace App\Filament\Super\Widgets;

use App\Models\Tenant;
use Filament\Widgets\ChartWidget;

/**
 * Registros de negocios por mes (últimos 6) — el pulso de crecimiento.
 */
class RegistrosChart extends ChartWidget
{
    protected ?string $heading = 'Neue Geschäfte pro Monat';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $meses = collect(range(5, 0))
            ->map(fn (int $i) => now()->subMonthsNoOverflow($i)->startOfMonth());

        $valores = $meses->map(fn ($mes): int => Tenant::query()
            ->whereBetween('created_at', [$mes, $mes->copy()->endOfMonth()])
            ->count());

        return [
            'datasets' => [[
                'label' => 'Registrierungen',
                'data' => $valores->all(),
                'borderColor' => '#e11d48',
                'backgroundColor' => 'rgba(225, 29, 72, 0.12)',
                'fill' => true,
                'tension' => 0.3,
            ]],
            'labels' => $meses->map(fn ($mes): string => $mes->locale('de')->isoFormat('MMM YY'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
