<?php

namespace App\Filament\Super\Resources;

use App\Filament\Super\Resources\TenantResource\Pages;
use App\Filament\Support\StepUpAuth;
use App\Mail\AnuncioMail;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Support\Planes;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Gestión de todos los negocios del SaaS (solo panel /super).
 * Aquí el operador da el "Zulassung": plan, trial, suspensión, API keys.
 */
class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Geschäfte';

    protected static ?string $modelLabel = 'Geschäft';

    protected static ?string $pluralModelLabel = 'Geschäfte';

    public const TIPOS = [
        'barberia' => 'Barbershop',
        'salon' => 'Friseursalon',
        'unas' => 'Nagelstudio',
        'spa' => 'Kosmetik / Spa',
        'restaurante' => 'Restaurant',
        'otro' => 'Sonstiges',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Geschäft')->schema([
                TextInput::make('nombre')->label('Name')->required()->maxLength(100)
                    ->regex('/^[\p{L}\p{N}\s\-\'.,&()\/+]+$/u'),
                Select::make('tipo')->label('Typ')->options(self::TIPOS)->required(),
                TextInput::make('ciudad')->label('Stadt')->maxLength(100),
                TextInput::make('telefono')->label('Telefon')->maxLength(30)
                    ->regex('/^[\d\s+\-()\/]+$/'),
                TextInput::make('email')->label('E-Mail')->email()->maxLength(255),
                TextInput::make('slug')->label('Buchungsseite (Slug)')
                    ->disabled()->dehydrated(false)
                    ->helperText('/reservar/{slug}'),
            ])->columns(2),

            Section::make('Inhaber-Konto')
                ->description('Beim Anlegen wird das Konto des Inhabers erstellt — das temporäre Passwort wird EINMAL angezeigt.')
                ->schema([
                    // Los campos inhaber_* no son columnas del tenant: la
                    // página CreateTenant los extrae antes del insert
                    TextInput::make('inhaber_nombre')->label('Name des Inhabers')
                        ->required()->maxLength(100)
                        ->regex('/^[\p{L}\s\-\'.]+$/u'),
                    TextInput::make('inhaber_email')->label('E-Mail des Inhabers')
                        ->required()->email()->maxLength(255)
                        ->unique(table: 'users', column: 'email'),
                ])->columns(2)
                ->visibleOn('create'),

            Section::make('Zulassung & Abrechnung')->schema([
                // El plan se sincroniza solo desde Stripe; este override manual
                // es para acuerdos especiales — queda auditado al guardar
                Select::make('plan')->label('Plan')
                    ->options(collect(Planes::todos())->map(fn (array $p): string => $p['nombre'].' ('.$p['precio'].' €)'))
                    ->required()
                    ->helperText('Achtung: wird bei der nächsten Stripe-Synchronisierung überschrieben, falls eine aktive Subscription existiert.'),
                DatePicker::make('trial_ends_at')->label('Testphase bis')
                    ->native(false)->displayFormat('d.m.Y'),
                Toggle::make('activo')->label('Aktiv (Zugang erlaubt)'),
            ])->columns(3),
        ]);
    }

    /** Vista de detalle: datos operativos con enlaces directos. */
    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Geschäft')->schema([
                TextEntry::make('nombre')->label('Name')->weight('bold'),
                TextEntry::make('tipo')->label('Typ')
                    ->formatStateUsing(fn (string $state): string => self::TIPOS[$state] ?? $state)->badge()->color('gray'),
                TextEntry::make('ciudad')->label('Stadt')->placeholder('—'),
                TextEntry::make('telefono')->label('Telefon')->placeholder('—')->copyable(),
                TextEntry::make('email')->label('E-Mail')->placeholder('—')->copyable(),
                TextEntry::make('created_at')->label('Registriert am')->date('d.m.Y'),
            ])->columns(3),

            Section::make('Betrieb')->schema([
                TextEntry::make('slug')
                    ->label('Online-Buchungsseite')
                    ->formatStateUsing(fn (string $state): string => url('/reservar/'.$state))
                    ->url(fn (Tenant $record): string => url('/reservar/'.$record->slug), shouldOpenInNewTab: true)
                    ->copyable()
                    ->icon('heroicon-m-globe-alt'),
                TextEntry::make('stripe_id')
                    ->label('Stripe-Kunde')
                    ->placeholder('Kein Stripe-Kunde')
                    ->url(fn (Tenant $record): ?string => $record->stripe_id
                        ? 'https://dashboard.stripe.com/customers/'.$record->stripe_id
                        : null, shouldOpenInNewTab: true)
                    ->icon('heroicon-m-credit-card'),
                TextEntry::make('plan')
                    ->label('Plan')
                    ->formatStateUsing(fn (?string $state): string => Planes::nombre($state))
                    ->badge()->color('info'),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('nombre')->label('Name')
                    ->searchable()->sortable()->weight('bold')
                    ->description(fn (Tenant $record): string => '/'.$record->slug),
                Tables\Columns\TextColumn::make('tipo')->label('Typ')->badge()->color('gray')
                    ->formatStateUsing(fn (string $state): string => self::TIPOS[$state] ?? $state),
                Tables\Columns\TextColumn::make('ciudad')->label('Stadt')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('plan')->label('Plan')->badge()
                    ->formatStateUsing(fn (?string $state): string => Planes::nombre($state))
                    ->color('info'),
                Tables\Columns\TextColumn::make('estado')->label('Status')->badge()
                    ->state(fn (Tenant $record): string => $record->estadoSuscripcion())
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'activa' => 'Zahlend',
                        'trial' => 'Testphase',
                        'vencido' => 'Abgelaufen',
                        'suspendido' => 'Gesperrt',
                        default => $state,
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'activa' => 'success',
                        'trial' => 'info',
                        'vencido' => 'warning',
                        'suspendido' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('trial_ends_at')->label('Trial bis')
                    ->date('d.m.Y')->sortable()->placeholder('—'),
                Tables\Columns\TextColumn::make('users_count')->label('Benutzer')
                    ->counts('users'),
                Tables\Columns\TextColumn::make('created_at')->label('Registriert')
                    ->date('d.m.Y')->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('plan')->label('Plan')
                    ->options(collect(Planes::todos())->map(fn (array $p): string => $p['nombre'])),
                Tables\Filters\SelectFilter::make('tipo')->label('Typ')->options(self::TIPOS),
                Tables\Filters\TernaryFilter::make('activo')->label('Aktiv'),
            ])
            ->recordUrl(fn (Tenant $record): string => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),

                Action::make('trial_verlaengern')
                    ->label('Trial +14 Tage')
                    ->icon('heroicon-m-clock')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (Tenant $record): void {
                        $base = $record->trial_ends_at?->isFuture()
                            ? $record->trial_ends_at
                            : now();
                        $record->update(['trial_ends_at' => $base->copy()->addDays(14)]);

                        AuditLog::registrar('SAAS_TRIAL_EXTENDIDO', 'Tenant', $record->id, null, [
                            'hasta' => $record->trial_ends_at->toDateString(),
                        ]);

                        Notification::make()
                            ->title('Testphase verlängert')
                            ->body('Bis '.$record->trial_ends_at->format('d.m.Y'))
                            ->success()->send();
                    }),

                Action::make('suspender')
                    ->label(fn (Tenant $record): string => $record->activo ? 'Sperren' : 'Freischalten')
                    ->icon(fn (Tenant $record): string => $record->activo ? 'heroicon-m-lock-closed' : 'heroicon-m-lock-open')
                    ->color(fn (Tenant $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalDescription(fn (Tenant $record): string => $record->activo
                        ? 'Das Geschäft und seine Buchungsseite werden sofort gesperrt.'
                        : 'Das Geschäft erhält wieder vollen Zugang.')
                    ->action(function (Tenant $record): void {
                        $record->update(['activo' => ! $record->activo]);

                        AuditLog::registrar(
                            $record->activo ? 'SAAS_TENANT_REACTIVADO' : 'SAAS_TENANT_SUSPENDIDO',
                            'Tenant',
                            $record->id,
                        );

                        Notification::make()
                            ->title($record->activo ? 'Geschäft freigeschaltet' : 'Geschäft gesperrt')
                            ->success()->send();
                    }),

                Action::make('nachricht')
                    ->label('Nachricht senden')
                    ->icon('heroicon-m-envelope')
                    ->color('info')
                    ->form([
                        TextInput::make('asunto')->label('Betreff')->required()->maxLength(150),
                        Textarea::make('mensaje')->label('Nachricht')
                            ->required()->rows(6)->maxLength(5000),
                    ])
                    ->action(function (Tenant $record, array $data): void {
                        $enviados = self::enviarAnuncio($record, $data['asunto'], $data['mensaje']);

                        Notification::make()
                            ->title("Nachricht an {$enviados} Empfänger gesendet")
                            ->success()->send();
                    }),

                // Offboarding definitivo (GDPR Art. 17 a nivel de negocio):
                // borra el tenant y TODOS sus datos (cascade) + sus usuarios
                DeleteAction::make()
                    ->label('Endgültig löschen')
                    ->modalHeading('Geschäft endgültig löschen?')
                    ->modalDescription('ALLE Daten (Kunden, Termine, Zahlungen, Benutzer) werden unwiderruflich gelöscht. Nur für Offboarding/DSGVO verwenden — zum Pausieren bitte "Sperren".')
                    // Zero Trust — step-up auth antes de borrar un tenant completo
                    ->schema([StepUpAuth::passwordField()])
                    ->before(function (Tenant $record): void {
                        AuditLog::registrar('SAAS_TENANT_ELIMINADO', 'Tenant', $record->id, null, [
                            'nombre' => $record->nombre,
                            'slug' => $record->slug,
                        ]);

                        // users.tenant_id es nullOnDelete → borrarlos explícitamente
                        // (si no, quedarían huérfanos con PII; CWE-459)
                        $record->users()->delete();
                    }),

                Action::make('api_key')
                    ->label('API-Key rotieren')
                    ->icon('heroicon-m-key')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Der alte Key wird sofort ungültig. Der neue wird nur EINMAL angezeigt.')
                    ->action(function (Tenant $record): void {
                        // Mismo esquema que el comando tenant:api-key — solo
                        // se guarda el hash SHA-256 (CWE-256)
                        $clave = 'pk_'.Str::random(40);
                        $record->forceFill(['api_key_hash' => hash('sha256', $clave)])->save();

                        AuditLog::registrar('SAAS_API_KEY_ROTADA', 'Tenant', $record->id);

                        Notification::make()
                            ->title('Neuer API-Key (einmalige Anzeige!)')
                            ->body($clave)
                            ->persistent()
                            ->warning()->send();
                    }),
            ]);
    }

    /**
     * Envía un comunicado del operador a la gestión del negocio
     * (propietario + admins activos). Devuelve el nº de destinatarios.
     */
    public static function enviarAnuncio(Tenant $tenant, string $asunto, string $mensaje): int
    {
        $destinatarios = $tenant->users()
            ->where('activo', true)
            ->whereIn('rol', ['propietario', 'admin'])
            ->whereNotNull('email')
            ->get();

        foreach ($destinatarios as $user) {
            Mail::to($user->email, $user->name)
                ->queue(new AnuncioMail(strip_tags($asunto), strip_tags($mensaje)));
        }

        AuditLog::registrar('SAAS_ANUNCIO_ENVIADO', 'Tenant', $tenant->id, null, [
            'asunto' => $asunto,
            'destinatarios' => $destinatarios->count(),
        ]);

        return $destinatarios->count();
    }

    public static function getRelations(): array
    {
        return [
            TenantResource\RelationManagers\UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            'create' => Pages\CreateTenant::route('/create'),
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
