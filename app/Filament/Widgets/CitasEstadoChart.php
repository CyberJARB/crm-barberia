<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Cita;
use Filament\Widgets\ChartWidget;

/**
 * Distribución de las citas del mes actual por estado (doughnut).
 * Filtrado por tenant (OWASP A01).
 */
class CitasEstadoChart extends ChartWidget
{
    use HasTenantContext;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Termine nach Status';

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 1;

    protected ?string $maxHeight = '300px';

    /**
     * estado interno => [etiqueta alemana, color]
     */
    protected const ESTADOS = [
        'pendiente' => ['Ausstehend', '#f59e0b'],        // amber
        'confirmada' => ['Bestätigt', '#22c55e'],        // green
        'en_progreso' => ['In Bearbeitung', '#06b6d4'],  // cyan
        'completada' => ['Abgeschlossen', '#3b82f6'],    // blue
        'cancelada' => ['Storniert', '#ef4444'],         // red
        'no_show' => ['Nicht erschienen', '#6b7280'],    // gray
    ];

    protected function getData(): array
    {
        $conteos = Cita::where('tenant_id', $this->currentTenantId())
            ->whereYear('fecha', now()->year)
            ->whereMonth('fecha', now()->month)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $labels = [];
        $data = [];
        $colores = [];

        foreach (self::ESTADOS as $estado => [$etiqueta, $color]) {
            $labels[] = $etiqueta;
            $data[] = (int) ($conteos[$estado] ?? 0);
            $colores[] = $color;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Termine',
                    'data' => $data,
                    'backgroundColor' => $colores,
                    'borderWidth' => 0,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => ['position' => 'bottom'],
            ],
        ];
    }
}
