<?php

namespace App\Filament\Resources\ClienteResource\Widgets;

use App\Models\Cliente;
use App\Models\Servicio;
use App\Support\ReporteTexto;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs del perfil de cliente: visitas, gasto, fiabilidad y servicio favorito.
 * El registro llega ya acotado por tenant desde la página (OWASP A01).
 */
class ClienteStatsWidget extends StatsOverviewWidget
{
    public ?Cliente $record = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cliente = $this->record;

        if ($cliente === null) {
            return [];
        }

        $gastoTotal = (float) $cliente->pagos()->sum('monto');

        $totalCitas = $cliente->citas()->count();
        $noShows = $cliente->citas()->where('estado', 'no_show')->count();
        $fiabilidad = $totalCitas > 0
            ? round((1 - $noShows / $totalCitas) * 100)
            : null;

        // Servicio más frecuente en sus citas completadas
        $favorito = Servicio::query()
            ->join('cita_servicios', 'cita_servicios.servicio_id', '=', 'servicios.id')
            ->join('citas', 'citas.id', '=', 'cita_servicios.cita_id')
            ->where('citas.cliente_id', $cliente->id)
            ->where('citas.estado', 'completada')
            ->groupBy('servicios.id', 'servicios.nombre')
            ->orderByRaw('COUNT(*) DESC')
            ->value('servicios.nombre');

        return [
            Stat::make('Besuche', (string) $cliente->total_visitas)
                ->description($cliente->ultima_visita !== null
                    ? 'Zuletzt am '.$cliente->ultima_visita->format('d.m.Y')
                    : 'Noch kein Besuch')
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Umsatz gesamt', ReporteTexto::euro($gastoTotal))
                ->description($cliente->total_visitas > 0
                    ? 'Ø '.ReporteTexto::euro($gastoTotal / max(1, $cliente->total_visitas)).' pro Besuch'
                    : '—')
                ->icon('heroicon-o-banknotes')
                ->color('success'),

            Stat::make('Zuverlässigkeit', $fiabilidad !== null ? $fiabilidad.' %' : '—')
                ->description($noShows.' Mal nicht erschienen')
                ->icon('heroicon-o-check-badge')
                ->color(match (true) {
                    $fiabilidad === null => 'gray',
                    $fiabilidad >= 90 => 'success',
                    $fiabilidad >= 70 => 'warning',
                    default => 'danger',
                }),

            Stat::make('Lieblingsservice', $favorito ?? '—')
                ->description('Meistgebuchte Dienstleistung')
                ->icon('heroicon-o-star')
                ->color('warning'),
        ];
    }
}
