<?php

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;

/**
 * OWASP A07 / CWE-307 — Registra login exitoso y resetea contador de intentos.
 * NIST SP 800-53 AC-7 — Automatic account lockout.
 */
class LoginSuccessListener
{
    public function handle(Login $event): void
    {
        if ($event->user instanceof User) {
            $event->user->registrarLoginExitoso();
        }
    }
}
