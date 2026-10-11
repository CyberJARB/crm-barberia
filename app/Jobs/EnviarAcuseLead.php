<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Lead;
use App\Services\Mensajeria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Acuse de recibo automático a un nuevo lead por WhatsApp/SMS.
 *
 * Cumplimiento UE/DE — el job revalida TODO en el momento del envío:
 * - Tenant con mensajería activa y plan que incluye WhatsApp
 * - Opt-in explícito del interesado (§7 UWG)
 * - DSGVO Art. 5(1)(c) minimización: solo nombre del negocio, sin datos de terceros
 * - Aviso de opt-out en el mensaje
 *
 * GDPR Art. 32 / CWE-312 — payload cifrado en la tabla jobs.
 */
class EnviarAcuseLead implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;  // CWE-400 — evita workers colgados por proveedor lento

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $leadId) {}

    public function handle(Mensajeria $mensajeria): void
    {
        $lead = Lead::withoutGlobalScopes()
            ->with('tenant')
            ->find($this->leadId);

        if ($lead === null || $lead->tenant === null) {
            return;
        }

        // Gating por plan — pudo bajar de plan tras entrar el lead
        if (! $lead->tenant->mensajeria_activa || ! $lead->tenant->permite('whatsapp')) {
            return;
        }

        // §7 UWG — sin consentimiento vigente no se envía nada
        if (! $lead->mensajeria_optin || empty($lead->telefono)) {
            return;
        }

        // Minimización de datos: solo el negocio, sin servicio ni datos de terceros
        $texto = "Vielen Dank für Ihre Anfrage bei {$lead->tenant->nombre}! "
            .'Wir melden uns in Kürze persönlich bei Ihnen. '
            .'Antworten Sie STOP, um keine Nachrichten mehr zu erhalten.';

        $mensajeria->enviar('whatsapp', $lead->telefono, $texto);

        // OWASP A09 — prueba de envío (sin contenido PII en el log)
        AuditLog::registrar('MENSAJE_ENVIADO', 'Lead', $lead->id, null, [
            'tipo' => 'acuse_lead',
            'canal' => 'whatsapp',
        ]);
    }

    public function failed(Throwable $e): void
    {
        AuditLog::registrar('MENSAJE_FALLO', 'Lead', $this->leadId, null, [
            'tipo' => 'acuse_lead',
            'error' => $e->getMessage(),
        ], 'error');
    }
}
