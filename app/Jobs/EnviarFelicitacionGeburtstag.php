<?php

namespace App\Jobs;

use App\Mail\GeburtstagMail;
use App\Models\AuditLog;
use App\Models\Cliente;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

// GDPR Art. 32 / CWE-312 — Payload cifrado en la tabla jobs (contiene IDs de datos de clientes)
class EnviarFelicitacionGeburtstag implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;  // CWE-400 — Evita workers colgados por SMTP lento

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly Cliente $cliente) {}

    public function handle(): void
    {
        $cliente = Cliente::withoutGlobalScopes()
            ->with('tenant')
            ->find($this->cliente->id);

        if ($cliente === null) {
            return;
        }
        if (! $cliente->activo || $cliente->anonimizado_en !== null) {
            return;
        }
        if (! $cliente->tenant->email_geburtstag_activo) {
            return;
        }
        if (empty($cliente->email)) {
            return;
        }

        $mailable = new GeburtstagMail($cliente);

        if ($cliente->tenant->smtp_host) {
            $cliente->tenant->crearMailer()
                ->to($cliente->email, $cliente->nombre)
                ->send($mailable);
        } else {
            Mail::to($cliente->email, $cliente->nombre)->send($mailable);
        }
    }

    public function failed(Throwable $e): void
    {
        AuditLog::registrar('EMAIL_FALLO', 'Cliente', $this->cliente->id, null, [
            'tipo' => 'geburtstag',
            'error' => $e->getMessage(),
        ], 'error');
    }
}
