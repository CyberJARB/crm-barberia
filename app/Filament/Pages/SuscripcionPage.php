<?php

namespace App\Filament\Pages;

use App\Support\Planes;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

/**
 * Página de facturación del tenant (/admin/suscripcion).
 *
 * OWASP A01 — Solo accesible para el propietario del tenant (canAccess).
 * Los pagos se gestionan 100% en Stripe (Checkout + Customer Portal):
 * ningún dato de tarjeta/IBAN toca este servidor (PCI DSS SAQ-A).
 */
class SuscripcionPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-credit-card';

    protected static \UnitEnum|string|null $navigationGroup = 'Einstellungen';

    protected string $view = 'filament.pages.suscripcion';

    protected static ?string $title = 'Abonnement';

    protected static ?string $slug = 'suscripcion';

    protected static ?int $navigationSort = 30;

    /** Estado calculado para la vista */
    public string $estado = 'vencida';

    public ?int $diasTrial = null;

    public ?string $finGracia = null;

    public function mount(): void
    {
        $tenant = auth()->user()->tenant;

        // Estado de la suscripción: activa / cancelada (grace) / trial / vencida
        $sub = $tenant->subscription('default');

        if ($sub?->onGracePeriod()) {
            $this->estado = 'cancelada';
            $this->finGracia = $sub->ends_at?->format('d.m.Y');
        } elseif ($sub?->valid()) {
            $this->estado = 'activa';
        } elseif ($tenant->onGenericTrial()) {
            $this->estado = 'trial';
            $this->diasTrial = max(0, (int) ceil(now()->diffInDays($tenant->trial_ends_at, false)));
        }

        // Retorno de Stripe Checkout
        if (request()->query('checkout') === 'success') {
            Notification::make()
                ->title('Zahlung erfolgreich')
                ->body('Ihr Abonnement wird aktiviert. Die Bestätigung erfolgt in wenigen Augenblicken.')
                ->success()
                ->send();
        }

        // Mensaje del middleware VerificarSuscripcion (trial expirado)
        if (session()->has('suscripcion_requerida')) {
            Notification::make()
                ->title('Testphase abgelaufen')
                ->body(session('suscripcion_requerida'))
                ->warning()
                ->persistent()
                ->send();
        }
    }

    /** OWASP A01 / RBAC — solo el propietario gestiona la facturación */
    public static function canAccess(): bool
    {
        return auth()->user()?->esPropietario() ?? false;
    }

    protected function getViewData(): array
    {
        $tenant = auth()->user()->tenant;

        return [
            'tenant' => $tenant,
            'planes' => Planes::todos(),
            'planActual' => $tenant->plan,
            'planNombre' => Planes::nombre($tenant->plan),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stripePortal')
                ->label('Stripe-Portal öffnen')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                // Solo si ya existe el customer en Stripe
                ->visible(fn (): bool => auth()->user()->tenant->hasStripeId())
                ->action(function () {
                    try {
                        // URL firmada por Stripe, generada bajo demanda (no en render)
                        $url = auth()->user()->tenant->billingPortalUrl(
                            route('filament.admin.pages.suscripcion')
                        );

                        return redirect($url);
                    } catch (Throwable $e) {
                        // CWE-209 — no exponer detalles internos de Stripe al usuario
                        report($e);

                        Notification::make()
                            ->title('Portal derzeit nicht erreichbar')
                            ->body('Bitte versuchen Sie es in wenigen Minuten erneut.')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
