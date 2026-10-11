<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Support\Planes;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;

/**
 * Sincroniza la columna `tenants.plan` con la suscripción real de Stripe.
 *
 * Cashier 16 solo emite WebhookReceived/WebhookHandled (no existe un evento
 * SubscriptionUpdated), así que escuchamos WebhookReceived y filtramos los
 * eventos customer.subscription.* relevantes.
 *
 * CWE-345 — Este listener solo se ejecuta DESPUÉS de que Cashier verificó
 * la firma del webhook con STRIPE_WEBHOOK_SECRET (VerifyWebhookSignature):
 * el payload es auténtico, nunca un POST arbitrario.
 */
class SyncPlanFromStripe
{
    private const EVENTOS = [
        'customer.subscription.created',
        'customer.subscription.updated',
    ];

    public function handle(WebhookReceived $event): void
    {
        $tipo = $event->payload['type'] ?? null;

        if (! in_array($tipo, self::EVENTOS, true)) {
            return;
        }

        $suscripcion = $event->payload['data']['object'] ?? [];

        // Solo sincronizar la suscripción principal del SaaS
        // (misma resolución de tipo que el WebhookController de Cashier)
        $tipoSuscripcion = $suscripcion['metadata']['type']
            ?? $suscripcion['metadata']['name']
            ?? 'default';

        if ($tipoSuscripcion !== 'default') {
            return;
        }

        // Equivalente tipado de Cashier::findBillable() (que declara devolver
        // el trait Billable y rompe el análisis estático)
        $customerId = $suscripcion['customer'] ?? null;

        if (! is_string($customerId) || $customerId === '') {
            return;
        }

        $tenant = Tenant::where('stripe_id', $customerId)->first();

        if ($tenant === null) {
            return;
        }

        // Mapear stripe_price_id -> plan interno (allowlist de config/cashier.php)
        $priceId = $suscripcion['items']['data'][0]['price']['id'] ?? null;
        $plan = Planes::desdePrecioId($priceId);

        if ($plan === null) {
            // Price desconocido: no tocar el plan, solo dejar rastro (ISO 27001 A.12.4)
            Log::warning('Webhook de Stripe con price_id no mapeado a ningún plan', [
                'tenant_id' => $tenant->id,
                'price_id' => $priceId,
                'evento' => $tipo,
            ]);

            return;
        }

        if ($tenant->plan !== $plan) {
            $tenant->forceFill(['plan' => $plan])->save();

            Log::info('Plan del tenant sincronizado desde Stripe', [
                'tenant_id' => $tenant->id,
                'plan' => $plan,
                'evento' => $tipo,
            ]);
        }
    }
}
