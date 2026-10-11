<?php

namespace App\Filament\Pages;

use App\Mail\TestEmailMail;
use App\Models\Tenant;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class EmailEinstellungen extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-envelope';

    protected static \UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected string $view = 'filament.pages.email-einstellungen';

    protected static ?string $title = 'E-Mail Einstellungen';

    protected static ?int $navigationSort = 20;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->esAdmin() ?? false;
    }

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        $this->form->fill(array_merge(
            $tenant->only([
                'smtp_host', 'smtp_port', 'smtp_username',
                'smtp_encryption', 'mail_from_address', 'mail_from_name',
                'email_confirmacion_activo', 'email_recordatorio_activo',
                'email_followup_activo', 'email_followup_horas',
                'email_geburtstag_activo',
                'resumen_activo', 'resumen_frecuencia',
                'mensajeria_activa',
            ]),
            ['smtp_password' => '']  // Never pre-fill password
        ));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('SMTP-Konfiguration')
                    ->description('Eingehende E-Mails werden über diesen Server verschickt.')
                    ->collapsible()
                    ->schema([
                        TextInput::make('smtp_host')
                            ->label('SMTP Host')
                            ->placeholder('smtp.gmail.com')
                            ->maxLength(255),
                        TextInput::make('smtp_port')
                            ->label('SMTP Port')
                            ->numeric()
                            ->default(587)
                            ->minValue(1)
                            ->maxValue(65535),
                        Select::make('smtp_encryption')
                            ->label('Verschlüsselung')
                            ->options(['tls' => 'TLS (empfohlen)', 'ssl' => 'SSL', 'none' => 'Keine'])
                            ->default('tls'),
                        TextInput::make('smtp_username')
                            ->label('Benutzername')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('smtp_password')
                            ->label('Passwort')
                            ->password()
                            ->revealable()
                            ->placeholder('Leer lassen um beizubehalten')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('Absender')
                    ->schema([
                        TextInput::make('mail_from_address')
                            ->label('Von E-Mail')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('mail_from_name')
                            ->label('Von Name')
                            ->maxLength(255),
                    ])->columns(2),

                Section::make('E-Mail-Typen')
                    ->description('Aktivieren oder deaktivieren Sie automatische E-Mails.')
                    ->schema([
                        Toggle::make('email_confirmacion_activo')
                            ->label('Terminbestätigung')
                            ->helperText('Sofort nach der Buchung'),
                        Toggle::make('email_recordatorio_activo')
                            ->label('Terminerinnerung')
                            ->helperText('24 Stunden vor dem Termin'),
                        Toggle::make('email_followup_activo')
                            ->label('Follow-up')
                            ->helperText('Nach dem Termin'),
                        TextInput::make('email_followup_horas')
                            ->label('Follow-up nach (Stunden)')
                            ->numeric()
                            ->default(2)
                            ->minValue(1)
                            ->maxValue(72)
                            ->visible(fn ($get) => $get('email_followup_activo')),
                        Toggle::make('email_geburtstag_activo')
                            ->label('Geburtstagsgrüße')
                            ->helperText('Automatische Glückwunsch-E-Mail am Geburtstag des Kunden (9:00 Uhr)'),
                    ])->columns(2),

                Section::make('WhatsApp & SMS')
                    ->description('Termin-Erinnerungen 24 h vorher. Es werden ausschließlich Erinnerungen versendet (DSGVO Art. 6 Abs. 1 lit. b) — und nur an Kunden, die in ihrem Profil ausdrücklich eingewilligt haben (§7 UWG).')
                    ->schema([
                        Toggle::make('mensajeria_activa')
                            ->label('WhatsApp/SMS-Erinnerungen aktivieren')
                            // Gating por plan: el server-side está en el job;
                            // esto es solo la UX de upsell
                            ->disabled(fn (): bool => ! (auth()->user()->tenant?->permite('whatsapp') ?? false))
                            ->helperText(fn (): string => (auth()->user()->tenant?->permite('whatsapp') ?? false)
                                ? 'Die Einwilligung verwalten Sie pro Kunde im Kundenprofil.'
                                : '🔒 Ab Plan Professionell verfügbar — Upgrade unter Einstellungen → Abonnement.'),
                    ]),

                Section::make('Geschäfts-Update für Sie')
                    ->description('Erhalten Sie automatisch eine Zusammenfassung: Termine, Umsatz, neue Kunden, Geburtstage und Warteliste — an alle Inhaber und Admins.')
                    ->schema([
                        Toggle::make('resumen_activo')
                            ->label('Update aktivieren')
                            ->live(),
                        Select::make('resumen_frecuencia')
                            ->label('Frequenz')
                            ->options(Tenant::RESUMEN_FRECUENCIAS)
                            ->default('manana')
                            ->visible(fn ($get) => $get('resumen_activo')),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $tenant = auth()->user()->tenant;

        // Only encrypt and save the password if a new one was provided
        if (! empty($data['smtp_password'])) {
            $data['smtp_password'] = Crypt::encryptString($data['smtp_password']);
        } else {
            unset($data['smtp_password']);
        }

        $tenant->update($data);

        Notification::make()
            ->title('Einstellungen gespeichert')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testEmail')
                ->label('Test-E-Mail senden')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Test-E-Mail senden')
                ->modalDescription('Es wird eine Test-E-Mail an '.(auth()->user()->email ?? '').' gesendet.')
                ->action(function (): void {
                    $tenant = auth()->user()->tenant;

                    if (! $tenant->smtp_host) {
                        Notification::make()
                            ->title('Kein SMTP konfiguriert')
                            ->body('Bitte speichern Sie zuerst die SMTP-Einstellungen.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $tenant->crearMailer()
                            ->to(auth()->user()->email, auth()->user()->name)
                            ->send(new TestEmailMail($tenant));

                        Notification::make()
                            ->title('Test-E-Mail gesendet')
                            ->body('E-Mail wurde erfolgreich an '.auth()->user()->email.' versendet.')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Fehler beim Senden')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
