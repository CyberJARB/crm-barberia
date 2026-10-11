<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Pago;
use Filament\Widgets\ChartWidget;

/**
 * Evolución de ingresos de los últimos 30 días (línea, indigo).
 * Filtrado por tenant (OWASP A01).
 */
class IngresosChart extends ChartWidget
{
    use HasTenantContext;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Einnahmen der letzten 30 Tage';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 2;

    protected ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $desde = now()->subDays(29)->startOfDay();

        $totalesPorDia = Pago::where('tenant_id', $this->currentTenantId())
            ->selectRaw('DATE(created_at) as dia, SUM(monto) as total')
            ->where('created_at', '>=', $desde)
            ->groupBy('dia')
            ->orderBy('dia')
            ->pluck('total', 'dia');

        $labels = [];
        $data = [];

        for ($i = 29; $i >= 0; $i--) {
            $dia = now()->subDays($i);
            $labels[] = $dia->format('d.m.');
            $data[] = round((float) ($totalesPorDia[$dia->toDateString()] ?? 0), 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Einnahmen (€)',
                    'data' => $data,
                    'borderColor' => '#6366f1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.15)',
                    'fill' => true,
                    'tension' => 0.3,
                    'pointRadius' => 2,
                    'pointHoverRadius' => 4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}
