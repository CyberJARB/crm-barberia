<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Models\Vale;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gutscheine asociados al cliente — solo lectura (la venta/anulación
 * se hace en el módulo Gutscheine).
 */
class ValesRelationManager extends RelationManager
{
    protected static string $relationship = 'vales';

    protected static ?string $title = 'Gutscheine';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Code')
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Code kopiert'),
                Tables\Columns\TextColumn::make('valor_inicial')
                    ->label('Wert')
                    ->money('EUR'),
                Tables\Columns\TextColumn::make('saldo')
                    ->label('Restguthaben')
                    ->money('EUR')
                    ->color(fn (Vale $record): string => (float) $record->saldo > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('valido_hasta')
                    ->label('Gültig bis')
                    ->date('d.m.Y')
                    ->placeholder('Unbegrenzt'),
            ])
            ->emptyStateHeading('Keine Gutscheine')
            ->emptyStateIcon('heroicon-o-gift');
    }
}
