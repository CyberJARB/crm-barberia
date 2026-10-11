<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PersonalResource\Pages;
use App\Filament\Resources\PersonalResource\RelationManagers\AusenciasRelationManager;
use App\Filament\Resources\PersonalResource\RelationManagers\HorariosRelationManager;
use App\Models\Personal;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class PersonalResource extends Resource
{
    protected static ?string $model = Personal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Mitarbeiter';

    protected static ?string $modelLabel = 'Mitarbeiter';

    protected static ?string $pluralModelLabel = 'Mitarbeiter';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'personal';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                // OWASP A03 / CWE-20 — Solo caracteres de nombre válidos
                TextInput::make('nombre')->label('Name')->required()->maxLength(255)
                    ->regex('/^[\p{L}\s\-\'.]+$/u'),
                TextInput::make('telefono')->label('Telefon')->tel()->maxLength(30)
                    ->regex('/^[\d\s+\-()\/]+$/'),
                TextInput::make('email')->label('E-Mail')->email()->maxLength(255),
                ColorPicker::make('color')->label('Farbe'),
                Toggle::make('activo')->label('Aktiv')->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ColorColumn::make('color')->label('Farbe'),
                Tables\Columns\TextColumn::make('nombre')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('telefono')->label('Telefon'),
                Tables\Columns\TextColumn::make('email')->label('E-Mail')->toggleable(),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Aktiv'),
            ])
            ->actions([
                EditAction::make(),
                // OWASP A01 / RBAC — Borrado solo para admin/propietario (refuerza PersonalPolicy)
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
        return [
            HorariosRelationManager::class,
            AusenciasRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPersonal::route('/'),
            'create' => Pages\CreatePersonal::route('/create'),
            'edit' => Pages\EditPersonal::route('/{record}/edit'),
        ];
    }
}
