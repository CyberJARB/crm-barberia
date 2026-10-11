<?php

namespace App\Filament\Resources\ClienteResource\RelationManagers;

use App\Support\ReporteTexto;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Historial de citas del cliente — solo lectura: los Termine se gestionan
 * en su propio módulo (estado, cobro, solapes).
 */
class CitasRelationManager extends RelationManager
{
    protected static string $relationship = 'citas';

    protected static ?string $title = 'Terminhistorie';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Datum')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hora_inicio')
                    ->label('Uhrzeit')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('personal.nombre')
                    ->label('Mitarbeiter')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('servicios.nombre')
                    ->label('Dienstleistungen')
                    ->badge()
                    ->color('gray')
                    ->limitList(3),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ReporteTexto::estado($state))
                    ->color(fn (string $state): string => ReporteTexto::estadoColor($state)),
                Tables\Columns\TextColumn::make('total')
                    ->label('Betrag')
                    ->money('EUR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Status')
                    ->options(ReporteTexto::estados()),
            ])
            ->emptyStateHeading('Noch keine Termine')
            ->emptyStateDescription('Dieser Kunde hatte bisher keine Termine.')
            ->emptyStateIcon('heroicon-o-calendar');
    }
}
