<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Support\ReporteTexto;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de pagos del cliente — solo lectura (los pagos nacen del
 * flujo de cobro de citas o de la venta de vales, nunca a mano aquí).
 */
class PagosRelationManager extends RelationManager
{
    protected static string $relationship = 'pagos';

    protected static ?string $title = 'Zahlungen';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Datum')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('monto')
                    ->label('Betrag')
                    ->money('EUR')
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('metodo')
                    ->label('Methode')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReporteTexto::metodo($state))
                    ->color(fn (string $state): string => $state === 'vale' ? 'warning' : 'gray'),
                Tables\Columns\TextColumn::make('referencia')
                    ->label('Referenz')
                    ->placeholder('—'),
            ])
            ->emptyStateHeading('Noch keine Zahlungen')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
