<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LeadResource\Pages;
use App\Models\Lead;
use App\Services\Leads;
use Filament\Actions\Action;
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

class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-funnel';

    protected static ?string $navigationLabel = 'Leads / Anfragen';

    protected static ?string $modelLabel = 'Lead';

    protected static ?string $pluralModelLabel = 'Leads';

    protected static \UnitEnum|string|null $navigationGroup = 'Vertrieb';

    protected static ?int $navigationSort = 1;

    /** Badge: leads nuevos sin tocar → urgencia visible en la navegación. */
    public static function getNavigationBadge(): ?string
    {
        $n = Lead::query()->nuevos()->count();

        return $n > 0 ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('nombre')
                    ->label('Name')
                    ->required()
                    ->maxLength(150)
                    ->rule('regex:/^[\pL\s\.\-\']+$/u'),
                TextInput::make('telefono')
                    ->label('Telefon')
                    ->tel()
                    ->maxLength(40),
                TextInput::make('email')
                    ->label('E-Mail')
                    ->email()
                    ->maxLength(150),
                Select::make('fuente')
                    ->label('Quelle')
                    ->options(Lead::FUENTES)
                    ->default('manual')
                    ->required(),
                TextInput::make('valor_estimado')
                    ->label('Geschätzter Wert (€)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(9999999.99)
                    ->step(0.01),
                Select::make('asignado_a')
                    ->label('Zuständig')
                    ->relationship('responsable', 'name')
                    ->searchable()
                    ->preload(),
                Toggle::make('mensajeria_optin')
                    ->label('WhatsApp-Einwilligung (§7 UWG)')
                    ->helperText('Nur mit ausdrücklicher Einwilligung dürfen automatische Nachrichten gesendet werden.'),
                Textarea::make('mensaje')
                    ->label('Nachricht')
                    ->rows(3)
                    ->maxLength(5000)
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Name')
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('fuente')
                    ->label('Quelle')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => Lead::FUENTES[$state] ?? $state),
                Tables\Columns\TextColumn::make('estado')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Lead::ESTADOS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'nuevo' => 'danger',
                        'contactado' => 'warning',
                        'cualificado' => 'info',
                        'propuesta' => 'primary',
                        'ganado' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('valor_estimado')
                    ->label('Wert')
                    ->money('EUR')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('responsable.name')
                    ->label('Zuständig')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Eingegangen')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Status')
                    ->options(Lead::ESTADOS),
                Tables\Filters\SelectFilter::make('fuente')
                    ->label('Quelle')
                    ->options(Lead::FUENTES),
            ])
            ->actions([
                Action::make('kontaktiert')
                    ->label('Kontaktiert')
                    ->icon('heroicon-m-phone')
                    ->color('warning')
                    ->visible(fn (Lead $record): bool => $record->puedeTransicionarA('contactado')
                        && auth()->user()->can('update', $record))
                    ->action(function (Lead $record): void {
                        app(Leads::class)->transicionar($record, 'contactado');
                        Notification::make()->title('Als kontaktiert markiert')->success()->send();
                    }),

                Action::make('gewonnen')
                    ->label('Gewonnen → Kunde')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Lead $record): bool => $record->puedeTransicionarA('ganado')
                        && auth()->user()->can('update', $record))
                    ->action(function (Lead $record): void {
                        $service = app(Leads::class);
                        $service->transicionar($record, 'ganado');
                        $service->convertir($record);
                        Notification::make()
                            ->title('Lead gewonnen — Kunde wurde angelegt')
                            ->success()->send();
                    }),

                Action::make('verloren')
                    ->label('Verloren')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->visible(fn (Lead $record): bool => $record->puedeTransicionarA('perdido')
                        && auth()->user()->can('update', $record))
                    ->form([
                        Select::make('motivo')
                            ->label('Grund')
                            ->options(Lead::MOTIVOS_PERDIDA)
                            ->required(),
                    ])
                    ->action(function (Lead $record, array $data): void {
                        app(Leads::class)->transicionar($record, 'perdido', $data['motivo']);
                        Notification::make()->title('Als verloren markiert')->send();
                    }),

                EditAction::make(),
            ])
            ->emptyStateHeading('Noch keine Leads')
            ->emptyStateDescription('Anfragen von Ihrer Website, WhatsApp oder Empfehlungen landen hier — kein Interessent geht mehr verloren.')
            ->emptyStateIcon('heroicon-o-funnel');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLeads::route('/'),
            'create' => Pages\CreateLead::route('/create'),
            'edit' => Pages\EditLead::route('/{record}/edit'),
        ];
    }
}
