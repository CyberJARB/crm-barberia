<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductoResource\Pages;
use App\Models\Cliente;
use App\Models\Producto;
use App\Support\ReporteTexto;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ProductoResource extends Resource
{
    protected static ?string $model = Producto::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'Produkte';

    protected static ?string $modelLabel = 'Produkt';

    protected static ?string $pluralModelLabel = 'Produkte';

    protected static ?int $navigationSort = 3;

    /** Badge: aviso de stock bajo (gestión de inventario proactiva). */
    public static function getNavigationBadge(): ?string
    {
        $bajos = Producto::query()->activos()->stockBajo()->count();

        return $bajos > 0 ? (string) $bajos : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Produkte mit niedrigem Lagerbestand';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                // OWASP A03 / CWE-20 — Solo caracteres de nombre de producto
                TextInput::make('nombre')->label('Name')->required()->maxLength(255)
                    ->regex('/^[\p{L}\p{N}\s\-\'.,&()\/+%]+$/u'),
                TextInput::make('categoria')->label('Kategorie')->maxLength(100)
                    ->regex('/^[\p{L}\p{N}\s\-\'.&]+$/u'),
                // CWE-1284 — Sin precios negativos ni overflow
                TextInput::make('precio')->label('Preis')->required()->numeric()->prefix('€')
                    ->minValue(0)->maxValue(config('security.input.max_money', 99999.99)),
                TextInput::make('stock')->label('Lagerbestand')->required()->numeric()
                    ->minValue(0)->maxValue(99999)->default(0),
                TextInput::make('stock_minimo')->label('Mindestbestand')->required()->numeric()
                    ->minValue(0)->maxValue(9999)->default(3)
                    ->helperText('Bei Erreichen wird eine Warnung angezeigt'),
                Toggle::make('activo')->label('Aktiv')->default(true),
                Textarea::make('descripcion')->label('Beschreibung')->rows(2)->columnSpanFull()->maxLength(2000),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Name')->searchable()->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('categoria')->label('Kategorie')->badge()->color('gray')->placeholder('—'),
                Tables\Columns\TextColumn::make('precio')->label('Preis')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Lager')
                    ->sortable()
                    ->badge()
                    ->color(fn (Producto $record): string => match (true) {
                        $record->stock === 0 => 'danger',
                        $record->tieneStockBajo() => 'warning',
                        default => 'success',
                    })
                    ->formatStateUsing(fn (Producto $record): string => $record->stock === 0
                        ? 'Ausverkauft'
                        : "{$record->stock} Stk."),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Aktiv'),
                Tables\Filters\Filter::make('stock_bajo')
                    ->label('Nur niedriger Bestand')
                    ->query(fn ($query) => $query->stockBajo()),
            ])
            ->actions([
                // Venta rápida desde la tabla — el flujo principal del módulo
                Action::make('verkaufen')
                    ->label('Verkaufen')
                    ->icon('heroicon-m-shopping-cart')
                    ->color('primary')
                    ->visible(fn (Producto $record): bool => $record->activo
                        && $record->stock > 0
                        && auth()->user()->can('update', $record))
                    ->modalHeading(fn (Producto $record): string => "{$record->nombre} verkaufen")
                    ->modalDescription(fn (Producto $record): string => 'Preis: '
                        .ReporteTexto::euro($record->precio)." · Lager: {$record->stock} Stk.")
                    ->modalSubmitActionLabel('Verkauf erfassen')
                    ->form(fn (Producto $record) => [
                        TextInput::make('cantidad')
                            ->label('Menge')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue($record->stock)
                            ->required(),
                        Select::make('metodo')
                            ->label('Zahlungsmethode')
                            ->options(ReporteTexto::metodos())
                            ->default('efectivo')
                            ->required(),
                        Select::make('cliente_id')
                            ->label('Kunde (optional)')
                            ->options(fn () => Cliente::query()->activos()->orderBy('nombre')->pluck('nombre', 'id'))
                            ->searchable()
                            ->nullable(),
                    ])
                    ->action(function (Producto $record, array $data): void {
                        // El global scope de tenant acota el lookup (OWASP A01)
                        $cliente = ! empty($data['cliente_id'])
                            ? Cliente::query()->find((int) $data['cliente_id'])
                            : null;

                        try {
                            $pago = $record->vender((int) $data['cantidad'], $data['metodo'], $cliente);
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        $record->refresh();

                        $aviso = $record->tieneStockBajo()
                            ? " — Achtung, nur noch {$record->stock} Stk. auf Lager!"
                            : '';

                        Notification::make()
                            ->title('Verkauf erfasst')
                            ->body(ReporteTexto::euro($pago->monto).' — '.$pago->referencia.$aviso)
                            ->success()->send();
                    }),

                EditAction::make(),
                // OWASP A01 / RBAC — Borrado solo para admin/propietario
                DeleteAction::make()
                    ->visible(fn () => auth()->user()->esAdmin()),
            ])
            ->emptyStateHeading('Noch keine Produkte')
            ->emptyStateDescription('Legen Sie Pflege- und Stylingprodukte an, um den Verkauf an der Kasse zu erfassen.')
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProductos::route('/'),
            'create' => Pages\CreateProducto::route('/create'),
            'edit' => Pages\EditProducto::route('/{record}/edit'),
        ];
    }
}
