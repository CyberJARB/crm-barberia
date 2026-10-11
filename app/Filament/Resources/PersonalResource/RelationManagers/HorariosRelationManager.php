<?php

namespace App\Filament\Resources\PersonalResource\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Arbeitszeiten del empleado (tabla horarios_personal).
 * El registro pertenece siempre al Personal padre — sin IDs del cliente
 * (OWASP A01: imposible asignar horarios a empleados de otro tenant).
 */
class HorariosRelationManager extends RelationManager
{
    protected static string $relationship = 'horarios';

    protected static ?string $title = 'Arbeitszeiten';

    public const DIAS = [
        0 => 'Montag',
        1 => 'Dienstag',
        2 => 'Mittwoch',
        3 => 'Donnerstag',
        4 => 'Freitag',
        5 => 'Samstag',
        6 => 'Sonntag',
    ];

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('dia_semana')
                ->label('Wochentag')
                ->options(self::DIAS)
                ->required(),
            TimePicker::make('hora_inicio')
                ->label('Von')
                ->seconds(false)
                ->required(),
            // CWE-1284 — coherencia temporal
            TimePicker::make('hora_fin')
                ->label('Bis')
                ->seconds(false)
                ->required()
                ->after('hora_inicio'),
            Toggle::make('activo')
                ->label('Aktiv')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('dia_semana')
            ->columns([
                Tables\Columns\TextColumn::make('dia_semana')
                    ->label('Wochentag')
                    ->formatStateUsing(fn ($state): string => self::DIAS[(int) $state] ?? '—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hora_inicio')->label('Von')->time('H:i'),
                Tables\Columns\TextColumn::make('hora_fin')->label('Bis')->time('H:i'),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
            ])
            ->headerActions([
                CreateAction::make()->label('Arbeitszeit hinzufügen'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->visible(fn (): bool => auth()->user()->esAdmin()),
            ])
            ->emptyStateHeading('Keine Arbeitszeiten hinterlegt')
            ->emptyStateDescription('Fügen Sie die regulären Arbeitszeiten dieses Mitarbeiters hinzu.');
    }
}
