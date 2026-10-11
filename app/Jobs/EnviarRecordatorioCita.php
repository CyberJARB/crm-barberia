<?php

namespace App\Jobs;

use App\Mail\RecordatorioCitaMail;
use App\Models\AuditLog;
use App\Models\Cita;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

// GDPR Art. 32 / CWE-312 — Payload cifrado en la tabla jobs (contiene IDs de datos de clientes)
class EnviarRecordatorioCita implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;  // CWE-400 — Evita workers colgados por SMTP lento

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly Cita $cita) {}

    public function handle(): void
    {
        $cita = $this->cita->fresh(['tenant', 'cliente', 'personal', 'servicios']);

        if ($cita->estaCancelada()) {
            return;
        }
        if (! $cita->tenant->email_recordatorio_activo) {
            return;
        }
        if ($cita->recordatorio_enviado) {
            return;
        }
        if (empty($cita->cliente->email)) {
            return;
        }

        $mailable = new RecordatorioCitaMail($cita);

        if ($cita->tenant->smtp_host) {
            $cita->tenant->crearMailer()
                ->to($cita->cliente->email, $cita->cliente->nombre)
                ->send($mailable);
        } else {
            Mail::to($cita->cliente->email, $cita->cliente->nombre)->send($mailable);
        }

        $cita->update(['recordatorio_enviado' => true]);
    }

    public function failed(Throwable $e): void
    {
        AuditLog::registrar('EMAIL_FALLO', 'Cita', $this->cita->id, null, [
            'tipo' => 'recordatorio',
            'error' => $e->getMessage(),
        ], 'error');
    }
}
