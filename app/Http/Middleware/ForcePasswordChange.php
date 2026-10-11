<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * NIST SP 800-63B / CWE-258 — Contraseñas temporales
 * Fuerza cambio de contraseña en el primer login o cuando el admin lo exige.
 */
class ForcePasswordChange
{
    private const RUTAS_PERMITIDAS = [
        'filament.admin.auth.logout',
        'filament.admin.pages.change-password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->debe_cambiar_password
            && ! in_array($request->route()?->getName(), self::RUTAS_PERMITIDAS, true)
        ) {
            session()->flash('warning', 'Sie müssen Ihr Passwort ändern, bevor Sie fortfahren können.');

            // Redirige al perfil de Filament donde el usuario puede cambiar la contraseña
            return redirect()->route('filament.admin.auth.profile');
        }

        return $next($request);
    }
}
