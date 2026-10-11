<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ValeResource\Pages;
use App\Models\Vale;
use App\Support\ReporteTexto;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValeResource extends Resource
{
    protected static ?string $model = Vale::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = 'Gutscheine';

    protected static ?string $modelLabel = 'Gutschein';

    protected static ?string $pluralModelLabel = 'Gutscheine';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Gutschein')
                ->description('Der Code wird automatisch und fälschungssicher generiert.')
                ->schema([
                    TextInput::make('codigo')
                        ->label('Code')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                    // CWE-1284 — importe acotado; inmutable tras la venta
                    TextInput::make('valor_inicial')
                        ->label('Wert')
                        ->required()
                        ->numeric()
                        ->prefix('€')
                        ->minValue(1)
                        ->maxValue(config('security.input.max_money', 99999.99))
                        ->disabledOn('edit')
                        ->dehydratedWhenDisabled(false),
                    TextInput::make('saldo')
                        ->label('Restguthaben')
                        ->prefix('€')
                        ->disabled()
                        ->dehydrated(false)
                        ->visibleOn('edit'),
                    // BGB §195 — 3 años de validez por defecto en Alemania
                    DatePicker::make('valido_hasta')
                        ->label('Gültig bis')
                        ->default(now()->addYears(3))
                        ->minDate(now())
                        ->native(false)
                        ->displayFormat('d.m.Y'),
                    Select::make('cliente_id')
                        ->label('Kunde (optional)')
                        ->relationship('cliente', 'nombre')
                        ->searchable()
                        ->preload()
                        ->nullable(),
                    Textarea::make('notas')
                        ->label('Notizen')
                        ->rows(2)
                        ->maxLength(2000)
                        ->columnSpanFull(),
                ])->columns(2),

            Section::make('Verkauf')
                ->description('Der Verkauf wird automatisch als Einnahme verbucht.')
                ->schema([
                    Select::make('metodo_venta')
                        ->label('Zahlungsmethode')
                        ->options(ReporteTexto::metodos())
                        ->default('efectivo')
                        ->required(),
                ])
                ->visibleOn('create'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Code')
                    ->fontFamily(FontFamily::Mono)
                    ->weight('bold')
                    ->copyable()
                    ->copyMessage('Code kopiert')
                    ->searchable(),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Vale $record): string => $record->estado())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'activo' => 'Aktiv',
                        'agotado' => 'Aufgebraucht',
                        'caducado' => 'Abgelaufen',
                        'anulado' => 'Storniert',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'activo' => 'success',
                        'agotado' => 'gray',
                        'caducado' => 'warning',
                        'anulado' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('valor_inicial')
                    ->label('Wert')
                    ->money('EUR')
                    ->sortable(),
                Tables\Columns\TextColumn::make('saldo')
                    ->label('Restguthaben')
                    ->money('EUR')
                    ->sortable()
                    ->color(fn (Vale $record): string => (float) $record->saldo > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('cliente.nombre')
                    ->label('Kunde')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('valido_hasta')
                    ->label('Gültig bis')
                    ->date('d.m.Y')
                    ->sortable()
                    ->placeholder('Unbegrenzt'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Verkauft am')
                    ->date('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Status')
                    ->options([
                        'canjeable' => 'Einlösbar',
                        'agotado' => 'Aufgebraucht',
                        'anulado' => 'Storniert',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'canjeable' => $query->whereNull('anulado_en')->where('saldo', '>', 0)
                            ->where(fn (Builder $q) => $q->whereNull('valido_hasta')->orWhereDate('valido_hasta', '>=', today())),
                        'agotado' => $query->where('saldo', '<=', 0),
                        'anulado' => $query->whereNotNull('anulado_en'),
                        default => $query,
                    }),
            ])
            ->actions([
                EditAction::make(),
                // Anulación irreversible y auditada — solo gestión (OWASP A01)
                Action::make('stornieren')
                    ->label('Stornieren')
                    ->icon('heroicon-m-no-symbol')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Gutschein stornieren?')
                    ->modalDescription('Der Gutschein kann danach nicht mehr eingelöst werden. Diese Aktion ist unwiderruflich.')
                    ->visible(fn (Vale $record): bool => ! $record->estaAnulado()
                        && auth()->user()->esAdmin())
                    ->action(function (Vale $record): void {
                        $record->anular();
                        Notification::make()->title('Gutschein storniert')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('Noch keine Gutscheine')
            ->emptyStateDescription('Verkaufen Sie Ihren ersten Gutschein — ideal für Stammkunden und Geschenke.')
            ->emptyStateIcon('heroicon-o-gift');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListVales::route('/'),
            'create' => Pages\CreateVale::route('/create'),
            'edit' => Pages\EditVale::route('/{record}/edit'),
        ];
    }
}
