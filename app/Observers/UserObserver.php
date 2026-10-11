<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * OWASP A07 / CWE-613 — Insuficiente expiración de sesión
 * Cuando el rol o el estado activo de un usuario cambia,
 * se invalidan todas sus sesiones activas inmediatamente.
 */
class UserObserver
{
    public function updated(User $user): void
    {
        $invalidar = false;

        // Cambio de rol → invalidar sesiones (privilegio puede haber subido o bajado)
        if ($user->wasChanged('rol')) {
            $invalidar = true;
            Log::channel('security')->info('ROL_CAMBIADO', [
                'user_id' => $user->id,
                'rol_antes' => $user->getOriginal('rol'),
                'rol_nuevo' => $user->rol,
            ]);
            AuditLog::registrar('ROL_CAMBIADO', 'User', $user->id, [
                'rol' => $user->getOriginal('rol'),
            ], [
                'rol' => $user->rol,
            ], 'ok');
        }

        // Desactivar cuenta → forzar logout inmediato
        if ($user->wasChanged('activo') && ! $user->activo) {
            $invalidar = true;
            Log::channel('security')->warning('CUENTA_DESACTIVADA', ['user_id' => $user->id]);
        }

        if ($invalidar) {
            $this->invalidarSesiones($user);
        }
    }

    private function invalidarSesiones(User $user): void
    {
        // Elimina todas las sesiones del usuario de la tabla sessions
        $eliminadas = DB::table('sessions')
            ->where('user_id', $user->id)
            ->delete();

        if ($eliminadas > 0) {
            Log::channel('security')->info('SESIONES_INVALIDADAS', [
                'user_id' => $user->id,
                'sesiones' => $eliminadas,
            ]);
        }
    }
}
