<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\Cita;
use App\Services\Mensajeria;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Recordatorio de cita por WhatsApp/SMS (24 h antes).
 *
 * Cumplimiento UE/DE — el job revalida TODO en el momento del envío:
 * - Tenant con mensajería activa y cita no cancelada
 * - Opt-in vigente del cliente (§7 UWG; pudo revocarlo tras agendar)
 * - DSGVO Art. 5(1)(c) minimización: el texto solo lleva negocio,
 *   fecha y hora — nunca servicio, notas ni datos de salud
 * - Aviso de opt-out en cada mensaje
 *
 * GDPR Art. 32 / CWE-312 — payload cifrado en la tabla jobs.
 */
class EnviarRecordatorioMensaje implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;  // CWE-400 — Evita workers colgados por proveedor lento

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly Cita $cita) {}

    public function handle(Mensajeria $mensajeria): void
    {
        $cita = Cita::withoutGlobalScopes()
            ->with(['tenant', 'cliente'])
            ->find($this->cita->id);

        if ($cita === null || $cita->estaCancelada()) {
            return;
        }
        if (! $cita->tenant->mensajeria_activa) {
            return;
        }
        // Gating por plan — pudo bajar de plan tras agendarse la cita
        if (! $cita->tenant->permite('whatsapp')) {
            return;
        }

        $cliente = $cita->cliente;

        // §7 UWG — sin consentimiento vigente no se envía nada
        if ($cliente === null || ! $cliente->mensajeria_optin || $cliente->anonimizado_en !== null) {
            return;
        }
        if (empty($cliente->telefono)) {
            return;
        }

        $fecha = $cita->fecha->locale('de')->isoFormat('dd, DD.MM.');
        $hora = substr((string) $cita->hora_inicio, 0, 5);

        // Minimización de datos: sin servicio, sin notas, sin apellidos de terceros
        $texto = "Erinnerung von {$cita->tenant->nombre}: Ihr Termin am {$fecha} um {$hora} Uhr. "
            .'Bei Verhinderung bitte rechtzeitig absagen. '
            .'Antworten Sie STOP, um keine Erinnerungen mehr zu erhalten.';

        $mensajeria->enviar($cliente->mensajeria_canal, $cliente->telefono, $texto);

        // OWASP A09 — prueba de envío (sin contenido PII en el log)
        AuditLog::registrar('MENSAJE_ENVIADO', 'Cita', $cita->id, null, [
            'tipo' => 'recordatorio_24h',
            'canal' => $cliente->mensajeria_canal,
        ]);
    }

    public function failed(Throwable $e): void
    {
        AuditLog::registrar('MENSAJE_FALLO', 'Cita', $this->cita->id, null, [
            'tipo' => 'recordatorio_24h',
            'error' => $e->getMessage(),
        ], 'error');
    }
}
