<?php

namespace App\Filament\Super\Resources\TenantResource\RelationManagers;

use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Usuarios del negocio — herramientas de soporte del operador del SaaS.
 * Todas las acciones quedan auditadas (OWASP A09); el operador nunca
 * ve ni elige contraseñas definitivas (CWE-521).
 */
class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Benutzer';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Name')->searchable(),
                Tables\Columns\TextColumn::make('email')->label('E-Mail')->searchable()->copyable(),
                Tables\Columns\TextColumn::make('rol')->label('Rolle')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'propietario' => 'primary',
                        'admin' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('activo')->label('Aktiv')->boolean(),
                Tables\Columns\IconColumn::make('bloqueado')
                    ->label('Gesperrt')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => $record->estaBloqueado())
                    ->trueIcon('heroicon-o-lock-closed')
                    ->trueColor('danger')
                    ->falseIcon('heroicon-o-lock-open')
                    ->falseColor('gray'),
                Tables\Columns\TextColumn::make('ultimo_login')->label('Letzter Login')
                    ->dateTime('d.m.Y H:i')->placeholder('Nie'),
            ])
            ->actions([
                // === Soporte: desbloquear cuenta tras intentos fallidos ===
                Action::make('entsperren')
                    ->label('Entsperren')
                    ->icon('heroicon-m-lock-open')
                    ->color('warning')
                    ->visible(fn (User $record): bool => $record->estaBloqueado())
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['intentos_fallidos' => 0, 'bloqueado_hasta' => null]);

                        AuditLog::registrar('SAAS_USER_DESBLOQUEADO', 'User', $record->id);

                        Notification::make()->title('Benutzer entsperrt')->success()->send();
                    }),

                // === Soporte: contraseña temporal (mostrada una vez) ===
                Action::make('passwort_reset')
                    ->label('Passwort zurücksetzen')
                    ->icon('heroicon-m-key')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('Es wird ein temporäres Passwort erzeugt (einmalige Anzeige). Der Benutzer muss es beim nächsten Login ändern.')
                    ->action(function (User $record): void {
                        $temporal = Str::password(16);

                        $record->guardarEnHistorial();
                        $record->update([
                            'password' => Hash::make($temporal),
                            'debe_cambiar_password' => true,
                        ]);

                        AuditLog::registrar('SAAS_PASSWORT_RESET', 'User', $record->id);

                        Notification::make()
                            ->title('Temporäres Passwort (einmalige Anzeige!)')
                            ->body($temporal)
                            ->persistent()
                            ->warning()->send();
                    }),

                // === Soporte: activar/desactivar ===
                Action::make('toggle_aktiv')
                    ->label(fn (User $record): string => $record->activo ? 'Deaktivieren' : 'Aktivieren')
                    ->icon('heroicon-m-power')
                    ->color(fn (User $record): string => $record->activo ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['activo' => ! $record->activo]);

                        AuditLog::registrar(
                            $record->activo ? 'SAAS_USER_ACTIVADO' : 'SAAS_USER_DESACTIVADO',
                            'User',
                            $record->id,
                        );

                        Notification::make()->title('Gespeichert')->success()->send();
                    }),

                // === Soporte: entrar como el usuario (impersonación) ===
                Action::make('support_login')
                    ->label('Support-Login')
                    ->icon('heroicon-m-arrow-right-end-on-rectangle')
                    ->color('primary')
                    ->visible(fn (User $record): bool => $record->activo && ! $record->esSuperAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Als dieser Benutzer anmelden?')
                    ->modalDescription('Ihre Betreiber-Sitzung wird beendet und Sie arbeiten im Panel des Geschäfts. Die Aktion wird protokolliert.')
                    ->action(function (User $record) {
                        // Auditar ANTES del cambio de sesión, con la identidad
                        // del operador todavía activa (OWASP A09 / no repudio)
                        AuditLog::registrar('SAAS_IMPERSONATION', 'User', $record->id, null, [
                            'operador' => Auth::user()?->email,
                            'objetivo' => $record->email,
                        ]);

                        Auth::login($record);
                        session()->regenerate();   // CWE-384 — anti session fixation

                        return redirect('/admin');
                    }),
            ]);
    }
}
