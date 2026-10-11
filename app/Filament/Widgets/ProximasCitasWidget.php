<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\HasTenantContext;
use App\Models\Cita;
use App\Support\ReporteTexto;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Citas de hoy ordenadas por hora — visión operativa inmediata.
 * Filtrado por tenant (OWASP A01).
 */
class ProximasCitasWidget extends TableWidget
{
    use HasTenantContext;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Heutige Termine')
            ->query(
                Cita::query()
                    ->where('tenant_id', $this->currentTenantId())
                    ->whereDate('fecha', today())
                    ->with(['cliente', 'personal'])
                    ->orderBy('hora_inicio')
                    ->limit(10)
            )
            ->paginated(false)
            ->columns([
                Tables\Columns\TextColumn::make('hora_inicio')
                    ->label('Uhrzeit')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Kunde'),
                Tables\Columns\TextColumn::make('personal.nombre')
                    ->label('Mitarbeiter'),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReporteTexto::estado($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pendiente' => 'warning',
                        'confirmada' => 'success',
                        'en_progreso' => 'info',
                        'completada' => 'info',
                        'cancelada' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('total')
                    ->label('Betrag')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 2, ',', '.').' €'),
            ])
            ->emptyStateHeading('Keine Termine heute')
            ->emptyStateDescription('Für heute sind keine Termine geplant.')
            ->emptyStateIcon('heroicon-o-calendar')
            ->actions([])
            ->bulkActions([]);
    }
}
