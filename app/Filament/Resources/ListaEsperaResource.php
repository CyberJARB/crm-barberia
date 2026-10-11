<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ListaEsperaResource\Pages;
use App\Models\ListaEspera;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ListaEsperaResource extends Resource
{
    protected static ?string $model = ListaEspera::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Warteliste';

    protected static ?string $modelLabel = 'Wartelisten-Eintrag';

    protected static ?string $pluralModelLabel = 'Warteliste';

    protected static ?int $navigationSort = 6;

    /** Badge: cuántos siguen esperando hueco (hoy o futuro). */
    public static function getNavigationBadge(): ?string
    {
        $esperando = ListaEspera::query()
            ->esperando()
            ->whereDate('fecha_deseada', '>=', today())
            ->count();

        return $esperando > 0 ? (string) $esperando : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('cliente_id')
                    ->label('Kunde')
                    ->relationship('cliente', 'nombre')
                    ->searchable()
                    ->preload()
                    ->required(),
                Select::make('servicio_id')
                    ->label('Dienstleistung (optional)')
                    ->relationship('servicio', 'nombre')
                    ->preload()
                    ->nullable(),
                // CWE-1284 — solo fechas presentes/futuras tienen sentido
                DatePicker::make('fecha_deseada')
                    ->label('Wunschtermin')
                    ->required()
                    ->minDate(today())
                    ->native(false)
                    ->displayFormat('d.m.Y'),
                Select::make('franja')
                    ->label('Tageszeit')
                    ->options(ListaEspera::FRANJAS)
                    ->default('cualquiera')
                    ->required(),
                Textarea::make('notas')
                    ->label('Notizen')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha_deseada')
            ->columns([
                Tables\Columns\TextColumn::make('fecha_deseada')
                    ->label('Wunschtermin')
                    ->date('d.m.Y')
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Kunde')
                    ->searchable(),
                Tables\Columns\TextColumn::make('cliente.telefono')
                    ->label('Telefon')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('servicio.nombre')
                    ->label('Dienstleistung')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('franja')
                    ->label('Tageszeit')
                    ->formatStateUsing(fn (string $state): string => ListaEspera::FRANJAS[$state] ?? $state),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ListaEspera::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'esperando' => 'warning',
                        'avisado' => 'info',
                        'convertido' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Eingetragen am')
                    ->dateTime('d.m.Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Status')
                    ->options(ListaEspera::ESTADOS)
                    ->default('esperando'),
            ])
            ->actions([
                Action::make('benachrichtigt')
                    ->label('Benachrichtigt')
                    ->icon('heroicon-m-bell-alert')
                    ->color('info')
                    ->visible(fn (ListaEspera $record): bool => $record->estado === 'esperando'
                        && auth()->user()->can('update', $record))
                    ->action(function (ListaEspera $record): void {
                        $record->update(['estado' => 'avisado']);
                        Notification::make()->title('Als benachrichtigt markiert')->success()->send();
                    }),

                Action::make('termin_gebucht')
                    ->label('Termin gebucht')
                    ->icon('heroicon-m-calendar-days')
                    ->color('success')
                    ->visible(fn (ListaEspera $record): bool => in_array($record->estado, ['esperando', 'avisado'], true)
                        && auth()->user()->can('update', $record))
                    ->action(function (ListaEspera $record): void {
                        $record->update(['estado' => 'convertido']);
                        Notification::make()
                            ->title('Super! Eintrag als gebucht markiert')
                            ->body('Vergessen Sie nicht, den Termin im Kalender anzulegen.')
                            ->success()->send();
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->emptyStateHeading('Warteliste ist leer')
            ->emptyStateDescription('Tragen Sie Kunden ein, die auf einen freien Platz warten — bei Stornierungen sehen Sie sofort, wen Sie anrufen können.')
            ->emptyStateIcon('heroicon-o-queue-list');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListListaEspera::route('/'),
            'create' => Pages\CreateListaEspera::route('/create'),
            'edit' => Pages\EditListaEspera::route('/{record}/edit'),
        ];
    }
}
