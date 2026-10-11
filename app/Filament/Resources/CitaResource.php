<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CitaResource\Pages;
use App\Models\Ausencia;
use App\Models\Cita;
use App\Models\ListaEspera;
use App\Models\Servicio;
use App\Models\Vale;
use App\Support\ReporteTexto;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CitaResource extends Resource
{
    protected static ?string $model = Cita::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Termine';

    protected static ?string $modelLabel = 'Termin';

    protected static ?string $pluralModelLabel = 'Termine';

    protected static ?int $navigationSort = 4;

    /** Badge de navegación: terminas activos de hoy (visión operativa). */
    public static function getNavigationBadge(): ?string
    {
        $hoy = Cita::query()
            ->whereDate('fecha', today())
            ->whereIn('estado', Cita::ESTADOS_ACTIVOS)
            ->count();

        return $hoy > 0 ? (string) $hoy : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Offene Termine heute';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('cliente_id')
                    ->label('Kunde')
                    ->relationship('cliente', 'nombre')
                    ->searchable()->preload()->required()
                    // Walk-in: alta rápida de cliente sin salir del formulario.
                    // tenant_id lo inyecta BelongsToTenant::creating (OWASP A01)
                    ->createOptionForm([
                        TextInput::make('nombre')->label('Name')->required()->maxLength(255)
                            ->regex('/^[\p{L}\s\-\'.]+$/u'),
                        TextInput::make('telefono')->label('Telefon')->tel()->maxLength(30)
                            ->regex('/^[\d\s+\-()\/]+$/'),
                        TextInput::make('email')->label('E-Mail')->email()->maxLength(255),
                    ])
                    ->createOptionModalHeading('Neuen Kunden anlegen'),

                Select::make('personal_id')
                    ->label('Mitarbeiter')
                    ->relationship('personal', 'nombre')
                    ->searchable()->preload()->required()->live(),

                Select::make('servicios')
                    ->label('Dienstleistungen')
                    ->relationship('servicios', 'nombre', fn ($query) => $query->where('activo', true))
                    ->multiple()->searchable()->preload()->columnSpanFull()
                    ->live()
                    // Preis y Ende se calculan automáticamente de los servicios elegidos
                    ->afterStateUpdated(fn ($state, $get, $set) => self::recalcular($get, $set))
                    ->helperText('Preis und Endzeit werden automatisch berechnet.'),

                DatePicker::make('fecha')->label('Datum')->required()->live(),

                TimePicker::make('hora_inicio')->label('Beginn')->required()->seconds(false)
                    ->live()
                    ->afterStateUpdated(fn ($state, $get, $set) => self::recalcular($get, $set))
                    // CWE-1284 — Anti doble reserva: el horario del empleado no puede solaparse
                    ->rules([
                        fn ($get, ?Cita $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            $personalId = $get('personal_id');
                            $fecha = $get('fecha');
                            $horaFin = $get('hora_fin');
                            $tenantId = auth()->user()?->tenant_id;

                            if (! $personalId || ! $fecha || ! $horaFin || ! $tenantId) {
                                return;
                            }

                            if (Cita::haySolape((int) $tenantId, (int) $personalId, $fecha, $value, $horaFin, $record?->id)) {
                                $fail('Der Mitarbeiter hat zu dieser Zeit bereits einen Termin.');
                            }

                            // El empleado no debe estar de vacaciones/baja ese día
                            if (Ausencia::estaAusente((int) $tenantId, (int) $personalId, $fecha)) {
                                $fail('Der Mitarbeiter ist an diesem Tag abwesend (Urlaub/Krankheit).');
                            }
                        },
                    ]),

                // CWE-1284 — Coherencia temporal: fin posterior al inicio
                TimePicker::make('hora_fin')->label('Ende')->seconds(false)->after('hora_inicio')
                    ->helperText('Wird aus den Dienstleistungen berechnet — manuell anpassbar.'),

                Select::make('estado')
                    ->label('Status')
                    // Valores del enum de BD; etiquetas alemanas centralizadas
                    ->options(ReporteTexto::estados())
                    ->default('pendiente')->required()
                    // El ciclo de vida se gestiona con las acciones de la tabla (CWE-841);
                    // aquí solo se fija el estado inicial
                    ->disabledOn('edit')
                    ->dehydrated(fn (string $operation): bool => $operation === 'create'),

                // CWE-1284 — Rango de importe acotado (sin negativos ni overflow)
                TextInput::make('total')->label('Betrag')->numeric()->prefix('€')
                    ->minValue(0)->maxValue(config('security.input.max_money', 99999.99)),

                // OWASP A03 / CWE-79 — Límite de longitud en texto libre
                Textarea::make('notas')->label('Notizen')->rows(2)->columnSpanFull()
                    ->maxLength(config('security.input.max_notes_length', 5000)),
            ])->columns(2),
        ]);
    }

    /**
     * Recalcula Betrag y Endzeit a partir de los servicios seleccionados.
     * Los precios se leen SIEMPRE de la BD del tenant (nunca del request).
     */
    private static function recalcular(
        Get $get,
        Set $set,
    ): void {
        $ids = array_filter((array) $get('servicios'));

        if ($ids === []) {
            return;
        }

        // El global scope BelongsToTenant acota al tenant actual (OWASP A01)
        $servicios = Servicio::whereIn('id', $ids)->get(['precio', 'duracion_minutos']);

        $set('total', number_format((float) $servicios->sum('precio'), 2, '.', ''));

        $inicio = $get('hora_inicio');
        $duracion = (int) $servicios->sum('duracion_minutos');

        if ($inicio && $duracion > 0) {
            $set('hora_fin', Carbon::parse($inicio)->addMinutes($duracion)->format('H:i'));
        }
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('fecha', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('fecha')->label('Datum')->date('d.m.Y')->sortable(),
                Tables\Columns\TextColumn::make('hora_inicio')->label('Beginn')->time('H:i'),
                Tables\Columns\TextColumn::make('cliente.nombre')->label('Kunde')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('personal.nombre')->label('Mitarbeiter')->searchable(),
                Tables\Columns\TextColumn::make('estado')->label('Status')->badge()
                    ->formatStateUsing(fn (string $state): string => ReporteTexto::estado($state))
                    ->color(fn (string $state): string => ReporteTexto::estadoColor($state)),
                Tables\Columns\TextColumn::make('total')->label('Betrag')->money('EUR'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('estado')
                    ->label('Status')
                    ->options(ReporteTexto::estados()),
                Tables\Filters\Filter::make('hoy')
                    ->query(fn ($query) => $query->whereDate('fecha', today()))
                    ->label('Nur heute'),
            ])
            ->actions([
                // === Flujo operativo del día a día ===============================
                // Todas las transiciones pasan por Cita::transicionarA() (CWE-841,
                // auditado) y respetan CitaPolicy via can('update') (OWASP A01).

                Action::make('bestaetigen')
                    ->label('Bestätigen')
                    ->icon('heroicon-m-check')
                    ->color('success')
                    ->visible(fn (Cita $record): bool => $record->puedeTransicionarA('confirmada')
                        && auth()->user()->can('update', $record))
                    ->action(function (Cita $record): void {
                        $record->transicionarA('confirmada');
                        Notification::make()->title('Termin bestätigt')->success()->send();
                    }),

                Action::make('beginnen')
                    ->label('Beginnen')
                    ->icon('heroicon-m-play')
                    ->color('info')
                    ->visible(fn (Cita $record): bool => $record->puedeTransicionarA('en_progreso')
                        && auth()->user()->can('update', $record))
                    ->action(function (Cita $record): void {
                        $record->transicionarA('en_progreso');
                        Notification::make()->title('Termin begonnen')->success()->send();
                    }),

                Action::make('kassieren')
                    ->label('Kassieren')
                    ->icon('heroicon-m-banknotes')
                    ->color('primary')
                    ->visible(fn (Cita $record): bool => $record->puedeTransicionarA('completada')
                        && auth()->user()->can('update', $record))
                    ->modalHeading('Termin abschließen & kassieren')
                    ->modalDescription(fn (Cita $record): string => 'Zu zahlender Betrag: '
                        .ReporteTexto::euro($record->total))
                    ->modalSubmitActionLabel('Zahlung erfassen')
                    ->form([
                        Select::make('metodo')
                            ->label('Zahlungsmethode')
                            ->options(ReporteTexto::metodos())
                            ->default('efectivo')
                            ->required(),
                        TextInput::make('referencia')
                            ->label('Referenz (optional)')
                            ->maxLength(100)
                            ->regex('/^[\p{L}\p{N}\s\-_.\/#]+$/u'),
                        Toggle::make('mit_gutschein')
                            ->label('Gutschein einlösen')
                            ->live(),
                        TextInput::make('gutschein_code')
                            ->label('Gutschein-Code')
                            ->placeholder('GS-XXXX-XXXX')
                            ->maxLength(20)
                            ->regex('/^[A-Za-z0-9\-]+$/')
                            ->visible(fn (Get $get): bool => (bool) $get('mit_gutschein'))
                            ->required(fn (Get $get): bool => (bool) $get('mit_gutschein'))
                            // Validación en vivo: existe, es del tenant y tiene saldo
                            ->rule(static fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                $vale = Vale::query()->where('codigo', strtoupper(trim((string) $value)))->first();
                                if ($vale === null) {
                                    $fail('Gutschein nicht gefunden.');
                                } elseif (! $vale->esCanjeable()) {
                                    $fail('Gutschein ist nicht einlösbar (abgelaufen, storniert oder aufgebraucht).');
                                }
                            }),
                    ])
                    ->action(function (Cita $record, array $data): void {
                        // El global scope de tenant acota la búsqueda (OWASP A01)
                        $vale = ! empty($data['mit_gutschein']) && ! empty($data['gutschein_code'])
                            ? Vale::query()->where('codigo', strtoupper(trim($data['gutschein_code'])))->first()
                            : null;

                        try {
                            // Monto server-side desde la cita; método validado en cobrar()
                            $record->cobrar($data['metodo'], $data['referencia'] ?: null, $vale);
                        } catch (\DomainException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }

                        $detalle = ReporteTexto::euro($record->total)
                            .' — '.ReporteTexto::metodo($data['metodo']);
                        if ($vale !== null) {
                            $vale->refresh();
                            $detalle .= ' + Gutschein '.$vale->codigo
                                .' (Rest: '.ReporteTexto::euro($vale->saldo).')';
                        }

                        Notification::make()
                            ->title('Zahlung erfasst')
                            ->body($detalle)
                            ->success()->send();
                    }),

                ActionGroup::make([
                    EditAction::make(),

                    Action::make('no_show')
                        ->label('Nicht erschienen')
                        ->icon('heroicon-m-user-minus')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Als „Nicht erschienen" markieren?')
                        ->visible(fn (Cita $record): bool => $record->puedeTransicionarA('no_show')
                            && auth()->user()->can('update', $record))
                        ->action(function (Cita $record): void {
                            $record->transicionarA('no_show');
                            Notification::make()->title('Als „Nicht erschienen" markiert')->warning()->send();
                        }),

                    Action::make('stornieren')
                        ->label('Stornieren')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Termin stornieren?')
                        ->modalDescription('Der Termin wird storniert. Diese Aktion kann nicht rückgängig gemacht werden.')
                        ->visible(fn (Cita $record): bool => $record->puedeTransicionarA('cancelada')
                            && auth()->user()->can('update', $record))
                        ->action(function (Cita $record): void {
                            $record->transicionarA('cancelada');
                            Notification::make()->title('Termin storniert')->success()->send();

                            // Hueco libre → sugerir la Warteliste de ese día
                            $esperando = ListaEspera::esperandoPara(
                                $record->tenant_id,
                                $record->fecha->toDateString(),
                            );

                            if ($esperando > 0) {
                                Notification::make()
                                    ->title('Warteliste prüfen!')
                                    ->body("{$esperando} Kunde(n) warten auf einen Platz am {$record->fecha->format('d.m.Y')}.")
                                    ->warning()
                                    ->persistent()
                                    ->actions([
                                        Action::make('zur_warteliste')
                                            ->label('Zur Warteliste')
                                            ->url(ListaEsperaResource::getUrl('index'))
                                            ->button(),
                                    ])
                                    ->send();
                            }
                        }),

                    // OWASP A01 / RBAC — Borrado solo para admin/propietario (refuerza CitaPolicy)
                    DeleteAction::make()
                        ->visible(fn () => auth()->user()->esAdmin()),
                ]),
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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['cliente', 'personal', 'servicios']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCitas::route('/'),
            'create' => Pages\CreateCita::route('/create'),
            'edit' => Pages\EditCita::route('/{record}/edit'),
        ];
    }
}
