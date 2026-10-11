<?php

namespace App\Http\Controllers;

use App\Support\Planes;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Stripe Checkout para suscripciones (Laravel Cashier).
 *
 * - El webhook (firma verificada con STRIPE_WEBHOOK_SECRET, CWE-345) lo
 *   maneja Cashier automáticamente en POST /stripe/webhook.
 * - Rate limit throttle:10,1 aplicado en routes/web.php (OWASP A07 / CWE-770).
 */
class StripeController extends Controller
{
    /**
     * Crea una sesión de Stripe Checkout para el plan elegido.
     *
     * OWASP A04 / CWE-639 — El cliente envía solo el nombre del plan; el
     * Price ID se resuelve servidor-side desde config('cashier.prices').
     * Jamás se acepta un price_id arbitrario del formulario.
     */
    public function checkout(Request $request): mixed
    {
        $validated = $request->validate([
            'plan' => ['required', 'string', Rule::in(Planes::claves())],
        ]);

        $user = $request->user();

        // OWASP A01 — Solo el propietario puede gestionar la suscripción
        abort_unless($user->esPropietario(), 403, 'Nur der Inhaber kann das Abonnement verwalten.');

        $tenant = $user->tenant;
        abort_if($tenant === null, 403);

        $priceId = Planes::precioId($validated['plan']);

        if ($priceId === null) {
            // CWE-1188 — Stripe sin configurar: fallar de forma controlada
            Log::warning('Stripe checkout solicitado sin Price ID configurado', [
                'plan' => $validated['plan'],
                'tenant_id' => $tenant->id,
            ]);

            Notification::make()
                ->title('Zahlungen derzeit nicht verfügbar')
                ->body('Bitte versuchen Sie es später erneut oder kontaktieren Sie den Support.')
                ->warning()
                ->send();

            return redirect()->route('filament.admin.pages.suscripcion');
        }

        // Ya tiene suscripción válida: el cambio de plan se hace vía Stripe Portal
        if ($tenant->subscribed('default')) {
            Notification::make()
                ->title('Abonnement bereits aktiv')
                ->body('Nutzen Sie das Stripe-Portal, um Ihren Plan zu ändern.')
                ->info()
                ->send();

            return redirect()->route('filament.admin.pages.suscripcion');
        }

        // Checkout alojado en Stripe: la tarjeta/IBAN nunca toca nuestro
        // servidor (PCI DSS SAQ-A). SEPA-Lastschrift + tarjeta para Alemania.
        return $tenant
            ->newSubscription('default', $priceId)
            ->checkout([
                'success_url' => route('stripe.retorno').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('precios'),
                'payment_method_types' => ['card', 'sepa_debit'],
                'allow_promotion_codes' => true,
                'locale' => 'de',
            ]);
    }

    /**
     * Retorno tras un checkout exitoso: redirige al panel de suscripción.
     * La activación real del plan la confirma el webhook de Stripe
     * (SyncPlanFromStripe), nunca este redirect (no confiar en el cliente).
     */
    public function success(Request $request): RedirectResponse
    {
        Notification::make()
            ->title('Vielen Dank!')
            ->body('Ihr Abonnement wird aktiviert. Dies kann einen Moment dauern.')
            ->success()
            ->send();

        return redirect()->route('filament.admin.pages.suscripcion', ['checkout' => 'success']);
    }
}
