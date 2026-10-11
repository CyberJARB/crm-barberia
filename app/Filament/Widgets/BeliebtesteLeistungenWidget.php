<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Servicio;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Facades\DB;

/**
 * Beliebteste Leistungen (mes en curso): qué servicios se venden más y
 * cuánto ingresan. Excluye citas canceladas/no-show (servicios no realizados).
 *
 * Seguridad (OWASP A01): Servicio filtrado por tenant explícitamente; las
 * subconsultas sobre el pivote unen `citas` y filtran `citas.tenant_id`.
 */
class BeliebtesteLeistungenWidget extends TableWidget
{
    use HasTenantContext;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Beliebteste Leistungen (dieser Monat)';

    public function table(Table $table): Table
    {
        $tenantId = $this->currentTenantId();
        $year = now()->year;
        $month = now()->month;

        $base = fn () => DB::table('cita_servicios')
            ->join('citas', 'citas.id', '=', 'cita_servicios.cita_id')
            ->whereColumn('cita_servicios.servicio_id', 'servicios.id')
            ->where('citas.tenant_id', $tenantId)
            ->whereYear('citas.fecha', $year)
            ->whereMonth('citas.fecha', $month)
            ->whereNotIn('citas.estado', ['cancelada', 'no_show']);

        $anzahl = $base()->selectRaw('COUNT(*)');
        $umsatz = $base()->selectRaw('COALESCE(SUM(cita_servicios.precio), 0)');

        return $table
            ->query(
                Servicio::query()
                    ->where('tenant_id', $tenantId)
                    ->select('servicios.*')
                    ->selectSub($anzahl, 'anzahl_monat')
                    ->selectSub($umsatz, 'umsatz_monat')
                    ->whereExists($base())
                    ->orderByDesc('umsatz_monat')
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Leistung')
                    ->weight('bold'),
                TextColumn::make('categoria')
                    ->label('Kategorie')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('anzahl_monat')
                    ->label('Anzahl')
                    ->badge()
                    ->color('info'),
                TextColumn::make('umsatz_monat')
                    ->label('Umsatz')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' €')
                    ->weight('bold')
                    ->color('success'),
            ])
            ->emptyStateHeading('Noch keine Leistungen in diesem Monat');
    }
}
