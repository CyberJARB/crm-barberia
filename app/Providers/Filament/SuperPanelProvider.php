<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\RestrictSuperPanelIp;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Panel del OPERADOR del SaaS (/super) — gestión de todos los tenants.
 *
 * OWASP A01 — separación estricta: User::canAccessPanel solo deja entrar
 * al rol 'superadmin' (sin tenant); los usuarios de negocio no pueden.
 * Es la cuenta más sensible del sistema: misma pila de seguridad que el
 * panel admin (2FA TOTP, throttle, sesiones cifradas, CSRF).
 */
class SuperPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('super')
            ->path('super')
            ->login()
            ->brandName(fn (): string => config('saas.producto').' — '.config('saas.empresa'))
            ->colors([
                'primary' => Color::Rose,
                'gray' => Color::Slate,
            ])

            // OWASP A07 / NIST SP 800-63B AAL2 — TOTP también aquí
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Salon CRM Betreiber'),
            ], isRequired: (bool) config('security.mfa.required', false))

            ->discoverResources(in: app_path('Filament/Super/Resources'), for: 'App\Filament\Super\Resources')
            ->discoverPages(in: app_path('Filament/Super/Pages'), for: 'App\Filament\Super\Pages')
            ->discoverWidgets(in: app_path('Filament/Super/Widgets'), for: 'App\Filament\Super\Widgets')
            ->pages([Dashboard::class])

            ->middleware([
                RestrictSuperPanelIp::class,   // Zero Trust — IP allowlist (1º de todo)
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureTenantScope::class,   // no-op para superadmin (tenant null)
                ForcePasswordChange::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceIdleTimeout::class,
                ThrottleRequests::class.':api',
            ]);
    }
}
