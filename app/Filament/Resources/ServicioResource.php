<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServicioResource\Pages;
use App\Models\Servicio;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ServicioResource extends Resource
{
    protected static ?string $model = Servicio::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-scissors';

    protected static ?string $navigationLabel = 'Dienstleistungen';

    protected static ?string $modelLabel = 'Dienstleistung';

    protected static ?string $pluralModelLabel = 'Dienstleistungen';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                // OWASP A03 / CWE-20 — Solo letras, números, espacios y puntuación básica
                TextInput::make('nombre')->label('Name')->required()->maxLength(255)
                    ->regex('/^[\p{L}\p{N}\s\-\'.,&()\/+]+$/u'),
                TextInput::make('categoria')->label('Kategorie')->maxLength(100)
                    ->regex('/^[\p{L}\p{N}\s\-\'.&]+$/u'),
                // CWE-1284 — Rango acotado (5 min a 8 horas)
                TextInput::make('duracion_minutos')->label('Dauer')->required()->numeric()
                    ->minValue(5)->maxValue(480)->suffix('min'),
                // CWE-1284 — Sin precios negativos ni overflow
                TextInput::make('precio')->label('Preis')->required()->numeric()->prefix('€')
                    ->minValue(0)->maxValue(config('security.input.max_money', 99999.99)),
                ColorPicker::make('color')->label('Farbe'),
                Toggle::make('activo')->label('Aktiv')->default(true),
                // OWASP A03 / CWE-79 — Límite de longitud en texto libre
                Textarea::make('descripcion')->label('Beschreibung')->rows(3)->columnSpanFull()->maxLength(2000),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')->label('Farbe'),
                Tables\Columns\TextColumn::make('nombre')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('categoria')->label('Kategorie')->searchable()->badge(),
                Tables\Columns\TextColumn::make('duracion_minutos')->label('Dauer')->suffix(' min'),
                Tables\Columns\TextColumn::make('precio')->label('Preis')->money('EUR')->sortable(),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Aktiv'),
            ])
            ->actions([
                EditAction::make(),
                // OWASP A01 / RBAC — Borrado solo para admin/propietario (refuerza ServicioPolicy)
                DeleteAction::make()
                    ->visible(fn () => auth()->user()->esAdmin()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()->esAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServicios::route('/'),
            'create' => Pages\CreateServicio::route('/create'),
            'edit' => Pages\EditServicio::route('/{record}/edit'),
        ];
    }
}
