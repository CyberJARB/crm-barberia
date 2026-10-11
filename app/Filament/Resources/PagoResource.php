<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PagoResource\Pages;
use App\Models\Pago;
use App\Support\ReporteTexto;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PagoResource extends Resource
{
    protected static ?string $model = Pago::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Zahlungen';

    protected static ?string $modelLabel = 'Zahlung';

    protected static ?string $pluralModelLabel = 'Zahlungen';

    protected static ?int $navigationSort = 5;

    // GoBD / GDPR Art. 5(1)(f) — Registros financieros inmutables: prohibido borrar pagos
    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('cliente_id')
                    ->label('Kunde')
                    ->relationship('cliente', 'nombre')
                    ->searchable()->preload()->required(),
                Select::make('cita_id')
                    ->label('Termin')
                    ->relationship('cita', 'id')
                    ->searchable()->preload(),
                // CWE-1284 — Importe acotado: sin negativos (reembolso = registro propio) ni overflow
                TextInput::make('monto')->label('Betrag')->required()->numeric()->prefix('€')
                    ->minValue(0.01)->maxValue(config('security.input.max_money', 99999.99)),
                Select::make('metodo')
                    ->label('Zahlungsmethode')
                    // Valores del enum de BD; etiquetas alemanas centralizadas
                    ->options(ReporteTexto::metodos())
                    ->required(),
                // OWASP A03 / CWE-20 — Referencia alfanumérica (nº transacción/recibo)
                TextInput::make('referencia')->label('Referenz')->maxLength(100)
                    ->regex('/^[\p{L}\p{N}\s\-_.\/#]+$/u'),
                // OWASP A03 / CWE-79 — Límite de longitud en texto libre
                Textarea::make('notas')->label('Notizen')->rows(2)->columnSpanFull()->maxLength(2000),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('created_at')->date('d.m.Y')->sortable()->label('Datum'),
                Tables\Columns\TextColumn::make('cliente.nombre')->label('Kunde')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('monto')->label('Betrag')->money('EUR')->sortable(),
                Tables\Columns\TextColumn::make('metodo')->label('Methode')->badge()
                    ->formatStateUsing(fn (string $state): string => ReporteTexto::metodo($state))
                    ->color(fn (string $state): string => match ($state) {
                        'efectivo' => 'success',
                        'tarjeta' => 'primary',
                        'transferencia' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('referencia')->label('Referenz')->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('metodo')
                    ->label('Methode')
                    ->options(ReporteTexto::metodos()),
                Tables\Filters\Filter::make('hoy')
                    ->query(fn ($query) => $query->whereDate('created_at', today()))
                    ->label('Nur heute'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['cliente']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPagos::route('/'),
            'create' => Pages\CreatePago::route('/create'),
            'edit' => Pages\EditPago::route('/{record}/edit'),
        ];
    }
}
