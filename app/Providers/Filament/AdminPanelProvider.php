<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\CitasEstadoChart;
use App\Filament\Widgets\IngresosChart;
use App\Filament\Widgets\ProximasCitasWidget;
use App\Filament\Widgets\ResumenWidget;
use App\Filament\Widgets\SecurityStatsWidget;
use App\Http\Middleware\EnforceIdleTimeout;
use App\Http\Middleware\EnsureTenantScope;
use App\Http\Middleware\ForcePasswordChange;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login(Login::class)
            ->profile(EditProfile::class)
            // Marca blanca: cada negocio (barbería, salón, uñas…) ve su propio
            // nombre; el neutro 'Salon CRM' solo aparece sin sesión (login)
            ->brandName(fn (): string => auth()->user()?->tenant?->nombre ?? 'Salon CRM')
            ->colors([
                'primary' => Color::Indigo,
                'info' => Color::Sky,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'gray' => Color::Slate,
            ])

            // Identidad visual global (sin tema compilado: CSS inline)
            ->renderHook(
                'panels::head.end',
                fn (): View => view('filament.tema'),
            )

            // OWASP A07 — Login con throttle (5 intentos / 15 min)
            ->loginRouteSlug('login')
            // OWASP A07 / NIST SP 800-63B AAL2 — TOTP con códigos de recuperación
            ->multiFactorAuthentication([
                AppAuthentication::make()
                    ->recoverable()
                    ->brandName('Salon CRM'),
            ], isRequired: (bool) config('security.mfa.required', false))

            // reCAPTCHA v3 — inject script only on the login page
            ->renderHook(
                'panels::body.end',
                fn (): View => view('filament.auth.recaptcha'),
                scopes: Login::class,
            )

            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                ResumenWidget::class,
                IngresosChart::class,
                CitasEstadoChart::class,
                ProximasCitasWidget::class,
                SecurityStatsWidget::class,
            ])

            ->middleware([
                EncryptCookies::class,         // OWASP A07 — Cifra cookies de sesión
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,    // OWASP A07 — Invalida sesiones robadas
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,  // CWE-352 — Protección CSRF
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                EnsureTenantScope::class,      // OWASP A01 — Aislamiento de tenant
                ForcePasswordChange::class,    // NIST SP 800-63B — Fuerza cambio de contraseña temporal
            ])
            ->authMiddleware([
                Authenticate::class,
                EnforceIdleTimeout::class,   // Zero Trust — cierre por inactividad
                // OWASP A07 — Rate limiting en todas las rutas autenticadas
                ThrottleRequests::class.':api',
            ]);
    }
}
