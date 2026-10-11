<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * UA5 T01 / OWASP A07 / NIST SP 800-63B
 * Política de contraseñas: mínimo 12 caracteres, complejidad obligatoria.
 * Protege contra ataques de diccionario (CWE-521 Weak Password Requirements).
 */
class SecurePassword implements ValidationRule
{
    private const COMMON_PASSWORDS = [
        'password', '123456789', 'qwertyuiop', 'admin12345',
        'letmein', 'welcome1', 'password1', 'iloveyou',
        'sunshine', 'princess', 'monkey123', 'dragon123',
        'password123!', 'password123', 'password!', 'p@ssword123',
        'admin@2024', 'admin@2025', 'admin@2026', 'welcome123!',
        'qwerty123!', 'abc123456!', 'test1234!', 'user1234!',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (strlen($value) < 12) {
            $fail('Das Passwort muss mindestens 12 Zeichen lang sein.');

            return;
        }

        if (! preg_match('/[A-Z]/', $value)) {
            $fail('Das Passwort muss mindestens einen Großbuchstaben enthalten.');

            return;
        }

        if (! preg_match('/[a-z]/', $value)) {
            $fail('Das Passwort muss mindestens einen Kleinbuchstaben enthalten.');

            return;
        }

        if (! preg_match('/[0-9]/', $value)) {
            $fail('Das Passwort muss mindestens eine Zahl enthalten.');

            return;
        }

        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('Das Passwort muss mindestens ein Sonderzeichen enthalten (!@#$%^&*).');

            return;
        }

        if (in_array(strtolower($value), self::COMMON_PASSWORDS)) {
            $fail('Dieses Passwort ist zu verbreitet. Bitte wählen Sie ein sichereres Passwort.');
        }
    }
}
