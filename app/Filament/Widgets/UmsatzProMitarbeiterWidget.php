<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\Personal;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Umsatz pro Mitarbeiter (mes en curso): cuánto factura cada barbero.
 *
 * Seguridad (OWASP A01 — aislamiento de tenant):
 * - La consulta principal filtra Personal por tenant explícitamente.
 * - Las subconsultas de pagos/citas usan withoutGlobalScopes() + filtro
 *   explícito por tenant_id (determinista, no depende del scope global).
 */
class UmsatzProMitarbeiterWidget extends TableWidget
{
    use HasTenantContext;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Umsatz pro Mitarbeiter (dieser Monat)';

    public function table(Table $table): Table
    {
        $tenantId = $this->currentTenantId();
        $year = now()->year;
        $month = now()->month;

        $umsatz = Pago::withoutGlobalScopes()
            ->selectRaw('COALESCE(SUM(pagos.monto), 0)')
            ->join('citas', 'citas.id', '=', 'pagos.cita_id')
            ->whereColumn('citas.personal_id', 'personal.id')
            ->where('pagos.tenant_id', $tenantId)
            ->whereYear('pagos.created_at', $year)
            ->whereMonth('pagos.created_at', $month);

        $termine = Cita::withoutGlobalScopes()
            ->selectRaw('COUNT(*)')
            ->whereColumn('citas.personal_id', 'personal.id')
            ->where('citas.tenant_id', $tenantId)
            ->whereYear('citas.fecha', $year)
            ->whereMonth('citas.fecha', $month);

        return $table
            ->query(
                Personal::query()
                    ->where('tenant_id', $tenantId)
                    ->where('activo', true)
                    ->select('personal.*')
                    ->selectSub($umsatz, 'umsatz_monat')
                    ->selectSub($termine, 'termine_monat')
                    ->orderByDesc('umsatz_monat')
            )
            ->paginated(false)
            ->columns([
                TextColumn::make('nombre')
                    ->label('Mitarbeiter')
                    ->weight('bold'),
                TextColumn::make('termine_monat')
                    ->label('Termine')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('umsatz_monat')
                    ->label('Umsatz')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' €')
                    ->weight('bold')
                    ->color('success'),
            ])
            ->emptyStateHeading('Noch keine Umsätze in diesem Monat');
    }
}
