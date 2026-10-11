<?php

namespace App\Filament\Support;

use Filament\Forms\Components\TextInput;

/**
 * Step-up authentication (OWASP A07 / NIST 800-63B / Zero Trust "verify explicitly").
 *
 * Re-verificación explícita de identidad antes de una acción crítica:
 * el usuario debe reconfirmar su contraseña aunque ya tenga sesión activa.
 * Se añade al modal de cualquier Filament Action via ->schema([StepUpAuth::passwordField()]).
 */
class StepUpAuth
{
    public static function passwordField(): TextInput
    {
        return TextInput::make('step_up_password')
            ->label(__('Passwort zur Bestätigung'))
            ->helperText(__('Diese sicherheitskritische Aktion erfordert die erneute Eingabe deines Passworts.'))
            ->password()
            ->required()
            ->rule('current_password')
            ->validationMessages([
                'current_password' => __('Das Passwort ist nicht korrekt.'),
            ])
            ->dehydrated(false);
    }
}
