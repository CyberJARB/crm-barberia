<?php

namespace App\Filament\Resources\PersonalResource\RelationManagers;

use App\Models\Ausencia;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Abwesenheiten del empleado (vacaciones/baja). El registro pertenece
 * siempre al Personal padre (OWASP A01: sin IDs manipulables).
 * Los días cubiertos desaparecen de la disponibilidad pública y
 * bloquean la asignación en el formulario de Termine.
 */
class AusenciasRelationManager extends RelationManager
{
    protected static string $relationship = 'ausencias';

    protected static ?string $title = 'Abwesenheiten';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('fecha_inicio')
                ->label('Von')
                ->required()
                ->native(false)
                ->displayFormat('d.m.Y'),
            // CWE-1284 — coherencia temporal del rango
            DatePicker::make('fecha_fin')
                ->label('Bis')
                ->required()
                ->native(false)
                ->displayFormat('d.m.Y')
                ->afterOrEqual('fecha_inicio'),
            Select::make('motivo')
                ->label('Grund')
                ->options(Ausencia::MOTIVOS)
                ->default('vacaciones')
                ->required(),
            Textarea::make('notas')
                ->label('Notizen')
                ->rows(2)
                ->maxLength(1000),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_inicio', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fecha_inicio')
                    ->label('Von')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha_fin')
                    ->label('Bis')
                    ->date('d.m.Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('motivo')
                    ->label('Grund')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Ausencia::MOTIVOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'vacaciones' => 'info',
                        'enfermedad' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('notas')
                    ->label('Notizen')
                    ->limit(40)
                    ->placeholder('—'),
            ])
            ->headerActions([
                CreateAction::make()->label('Abwesenheit eintragen'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->esAdmin()),
            ])
            ->emptyStateHeading('Keine Abwesenheiten')
            ->emptyStateDescription('Urlaub oder Krankheit eintragen — diese Tage verschwinden automatisch aus der Online-Buchung.')
            ->emptyStateIcon('heroicon-o-sun');
    }
}
