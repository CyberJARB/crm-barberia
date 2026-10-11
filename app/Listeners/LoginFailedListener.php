<?php

namespace App\Listeners;

use App\Models\User;
use App\Support\SecurityLogger;
use Illuminate\Auth\Events\Failed;

/**
 * OWASP A07 / CWE-307 — Registra intento fallido e incrementa contador.
 * Después de 5 intentos la cuenta se bloquea por 15 minutos.
 */
class LoginFailedListener
{
    public function handle(Failed $event): void
    {
        $email = $event->credentials['email'] ?? null;

        if (! $email) {
            return;
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            $user->registrarIntentoFallido();
        } else {
            // El email no existe — loguear igual para detectar enumeración (CWE-204)
            SecurityLogger::loginFallido($email, request()->ip() ?? 'unknown');
        }
    }
}
