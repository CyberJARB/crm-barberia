<?php

namespace App\Filament\Super\Widgets;

use App\Models\Tenant;
use App\Support\Planes;
use Filament\Widgets\ChartWidget;

/**
 * Distribución de negocios activos por plan — mix de ingresos del SaaS.
 */
class PlanesChart extends ChartWidget
{
    protected ?string $heading = 'Geschäfte pro Plan';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $porPlan = Tenant::query()
            ->where('activo', true)
            ->selectRaw('plan, COUNT(*) as n')
            ->groupBy('plan')
            ->pluck('n', 'plan');

        $labels = [];
        $datos = [];
        foreach (Planes::claves() as $plan) {
            $labels[] = Planes::nombre($plan);
            $datos[] = (int) ($porPlan[$plan] ?? 0);
        }

        return [
            'datasets' => [[
                'data' => $datos,
                'backgroundColor' => ['#94a3b8', '#6366f1', '#e11d48'],
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
