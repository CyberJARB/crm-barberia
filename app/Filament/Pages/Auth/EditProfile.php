<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuditLog;
use App\Rules\NoPasswordReuse;
use App\Rules\SecurePassword;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Component;

/**
 * OWASP A07 / CWE-521 / NIST SP 800-63B
 * Perfil con validación de historial de contraseñas y política de seguridad.
 */
class EditProfile extends BaseEditProfile
{
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rules([
                new SecurePassword,
                new NoPasswordReuse(auth()->id()),
            ])
            ->helperText(
                'Mindestens 12 Zeichen: Groß- und Kleinbuchstaben, Zahl und Sonderzeichen. '.
                'Die letzten 5 Passwörter dürfen nicht wiederverwendet werden.'
            );
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $user = auth()->user();

        // Guardar contraseña actual en historial ANTES de sobrescribirla
        if (! empty($data['password'])) {
            $user->guardarEnHistorial();
            $data['password_changed_at'] = now();
            $data['debe_cambiar_password'] = false;

            AuditLog::registrar('PASSWORD_CAMBIADO', 'User', $user->id, null, null, 'ok');
        }

        return parent::mutateFormDataBeforeSave($data);
    }
}
