<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ClienteResource\Pages;
use App\Filament\Resources\ClienteResource\RelationManagers;
use App\Filament\Support\StepUpAuth;
use App\Models\AuditLog;
use App\Models\Cliente;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ClienteResource extends Resource
{
    protected static ?string $model = Cliente::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Kunden';

    protected static ?string $modelLabel = 'Kunde';

    protected static ?string $pluralModelLabel = 'Kunden';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'nombre';

    /** Búsqueda global (lupa superior): por nombre, teléfono o e-mail */
    public static function getGloballySearchableAttributes(): array
    {
        return ['nombre', 'telefono', 'email'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        if (! $record instanceof Cliente) {
            return [];
        }

        return array_filter([
            'Telefon' => $record->telefono,
            'E-Mail' => $record->email,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                TextInput::make('nombre')
                    ->label('Name')
                    ->required()->maxLength(255)
                    ->regex('/^[\p{L}\s\-\'.]+$/u'),
                // OWASP A03 / CWE-20 — Solo dígitos y símbolos de teléfono válidos
                TextInput::make('telefono')->label('Telefon')->tel()->maxLength(30)
                    ->regex('/^[\d\s+\-()\/]+$/'),
                TextInput::make('email')->label('E-Mail')->email()->maxLength(255),
                DatePicker::make('fecha_nacimiento')
                    ->label('Geburtsdatum')
                    ->maxDate(today())->minDate('1900-01-01'),
                Textarea::make('alergias')
                    ->label('Allergien')
                    ->rows(2)->columnSpanFull()->maxLength(1000),
                Textarea::make('notas')
                    ->label('Notizen')
                    ->rows(3)->columnSpanFull()->maxLength(5000),
                Toggle::make('activo')->label('Aktiv')->default(true),
            ])->columns(2),

            Section::make('Kommunikation')
                ->description('Termin-Erinnerungen per WhatsApp/SMS — nur mit ausdrücklicher Einwilligung des Kunden (§7 UWG). Die Einwilligung wird mit Zeitstempel protokolliert und ist jederzeit widerrufbar.')
                ->schema([
                    Toggle::make('mensajeria_optin')
                        ->label('WhatsApp/SMS-Erinnerungen erlaubt')
                        ->helperText('Der Kunde hat ausdrücklich zugestimmt.')
                        ->live(),
                    Select::make('mensajeria_canal')
                        ->label('Kanal')
                        ->options(['whatsapp' => 'WhatsApp', 'sms' => 'SMS'])
                        ->default('whatsapp')
                        ->visible(fn ($get) => $get('mensajeria_optin')),
                ])->columns(2),
        ]);
    }

    /** Vista de detalle (perfil del cliente) — solo lectura. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Kontakt')->schema([
                TextEntry::make('nombre')->label('Name')->weight('bold'),
                TextEntry::make('telefono')->label('Telefon')
                    ->icon('heroicon-m-phone')->placeholder('—')->copyable(),
                TextEntry::make('email')->label('E-Mail')
                    ->icon('heroicon-m-envelope')->placeholder('—')->copyable(),
                TextEntry::make('fecha_nacimiento')->label('Geburtsdatum')
                    ->date('d.m.Y')->placeholder('—'),
                IconEntry::make('activo')->label('Aktiv')->boolean(),
                TextEntry::make('created_at')->label('Kunde seit')->date('d.m.Y'),
            ])->columns(3),

            Section::make('Hinweise')->schema([
                TextEntry::make('alergias')->label('Allergien')
                    ->placeholder('Keine bekannt')
                    ->color('danger')
                    ->icon('heroicon-m-exclamation-triangle'),
                TextEntry::make('notas')->label('Notizen')
                    ->placeholder('—'),
            ])->columns(2)->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(fn (Cliente $record): string => static::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('telefono')->label('Telefon')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('E-Mail')->searchable()->toggleable(),
                Tables\Columns\TextColumn::make('total_visitas')->sortable()->label('Besuche'),
                Tables\Columns\TextColumn::make('ultima_visita')->label('Letzter Besuch')->dateTime('d.m.Y')->sortable()->toggleable(),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
                Tables\Columns\IconColumn::make('anonimizado_en')
                    ->label('Anonymisiert')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->anonimizado_en !== null)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('activo')->label('Aktiv'),
                Tables\Filters\Filter::make('anonimizados')
                    ->query(fn ($query) => $query->whereNotNull('anonimizado_en'))
                    ->label('Nur anonymisierte'),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                // === GDPR Art. 20 — Exportar datos personales ===
                Action::make('gdpr_exportar')
                    ->label('Daten exportieren')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Personenbezogene Daten exportieren')
                    ->modalDescription('Es wird eine JSON-Datei mit allen Daten des Kunden erstellt (DSGVO Art. 20).')
                    // Zero Trust — step-up auth antes de exportar datos personales
                    ->schema([StepUpAuth::passwordField()])
                    // OWASP A01 / GDPR Art. 32 — Exportación de datos personales solo admin/propietario
                    ->visible(fn ($record) => $record->anonimizado_en === null && auth()->user()->esAdmin())
                    ->action(function ($record) {
                        $datos = [
                            'exportado_en' => now()->toIso8601String(),
                            'base_legal' => 'GDPR Art. 20 — Portabilidad',
                            'nombre' => $record->nombre,
                            'email' => $record->email,
                            'telefono' => $record->telefono,
                            'fecha_nacimiento' => $record->fecha_nacimiento?->toDateString(),
                            'notas' => $record->notas,
                            'alergias' => $record->alergias,
                            'total_visitas' => $record->total_visitas,
                            // DSGVO Art. 7(1) — prueba del consentimiento de mensajería
                            'mensajeria_optin' => $record->mensajeria_optin,
                            'mensajeria_optin_en' => $record->mensajeria_optin_en?->toIso8601String(),
                            'mensajeria_canal' => $record->mensajeria_canal,
                        ];

                        $nombre = "gdpr_cliente_{$record->id}_".now()->format('Y-m-d').'.json';
                        Storage::put("gdpr/{$nombre}", json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                        AuditLog::registrar('GDPR_EXPORTAR', 'Cliente', $record->id, null, [
                            'archivo' => $nombre,
                        ], 'ok');

                        Notification::make()
                            ->title('Daten exportiert')
                            ->body("Gespeichert unter storage/app/gdpr/{$nombre}")
                            ->success()->send();
                    }),

                // === GDPR Art. 17 — Derecho al olvido ===
                Action::make('gdpr_anonimizar')
                    ->label('Anonymisieren')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Recht auf Vergessenwerden — DSGVO Art. 17')
                    ->modalDescription('Diese Aktion ersetzt ALLE personenbezogenen Daten des Kunden DAUERHAFT durch anonyme Daten. Sie kann nicht rückgängig gemacht werden.')
                    // Zero Trust — step-up auth antes de borrado irreversible
                    ->schema([StepUpAuth::passwordField()])
                    ->visible(fn ($record) => $record->anonimizado_en === null && auth()->user()->esPropietario())
                    ->action(function ($record) {
                        $record->anonimizar();

                        Notification::make()
                            ->title('Kunde anonymisiert')
                            ->body('Die personenbezogenen Daten wurden entfernt (DSGVO Art. 17).')
                            ->success()->send();
                    }),

                DeleteAction::make()
                    ->schema([StepUpAuth::passwordField()])
                    ->visible(fn () => auth()->user()->esAdmin()),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->schema([StepUpAuth::passwordField()])
                        ->visible(fn () => auth()->user()->esAdmin()),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CitasRelationManager::class,
            RelationManagers\PagosRelationManager::class,
            RelationManagers\ValesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListClientes::route('/'),
            'create' => Pages\CreateCliente::route('/create'),
            'view' => Pages\ViewCliente::route('/{record}'),
            'edit' => Pages\EditCliente::route('/{record}/edit'),
        ];
    }
}
