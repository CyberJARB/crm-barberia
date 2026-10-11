<?php

namespace App\Jobs;

use App\Mail\ResumenMail;
use App\Models\AuditLog;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\ListaEspera;
use App\Models\Pago;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Throwable;

// GDPR Art. 32 / CWE-312 — Payload cifrado en la tabla jobs
class EnviarResumenTenant implements ShouldBeEncrypted, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 60;  // CWE-400 — Evita workers colgados por SMTP lento

    public array $backoff = [60, 300, 900];

    public function __construct(public readonly Tenant $tenant) {}

    public function handle(): void
    {
        $tenant = $this->tenant->fresh();

        if ($tenant === null || ! $tenant->activo || ! $tenant->resumen_activo) {
            return;
        }

        $stats = $this->calcularStats($tenant);

        // Destinatarios: gestión activa del negocio con email
        $destinatarios = $tenant->users()
            ->where('activo', true)
            ->whereIn('rol', ['propietario', 'admin'])
            ->whereNotNull('email')
            ->get();

        foreach ($destinatarios as $user) {
            $mailable = new ResumenMail($tenant, $stats);

            if ($tenant->smtp_host) {
                $tenant->crearMailer()->to($user->email, $user->name)->send($mailable);
            } else {
                Mail::to($user->email, $user->name)->send($mailable);
            }
        }
    }

    /**
     * KPIs del momento — todos los queries acotados por tenant (OWASP A01).
     *
     * @return array<string, mixed>
     */
    private function calcularStats(Tenant $tenant): array
    {
        $tid = $tenant->id;

        $proximasCitas = Cita::withoutGlobalScopes()
            ->where('tenant_id', $tid)
            ->whereDate('fecha', today())
            ->whereIn('estado', Cita::ESTADOS_ACTIVOS)
            ->where('hora_inicio', '>=', now()->format('H:i:s'))
            ->with(['cliente', 'personal'])
            ->orderBy('hora_inicio')
            ->limit(6)
            ->get()
            ->map(fn (Cita $c): array => [
                'hora' => substr((string) $c->hora_inicio, 0, 5),
                'cliente' => $c->cliente?->nombre ?? '—',
                'personal' => $c->personal?->nombre ?? 'Ohne Zuweisung',
            ])
            ->all();

        return [
            'citasHoy' => Cita::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->whereDate('fecha', today())
                ->whereIn('estado', Cita::ESTADOS_ACTIVOS)
                ->count(),
            'proximasCitas' => $proximasCitas,
            'nuevosClientes24h' => Cliente::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->where('created_at', '>=', now()->subDay())
                ->count(),
            'ingresosHoy' => (float) Pago::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->whereDate('created_at', today())
                ->sum('monto'),
            'ingresosAyer' => (float) Pago::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->whereDate('created_at', today()->subDay())
                ->sum('monto'),
            'cumpleanosHoy' => Cliente::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->where('activo', true)
                ->whereNull('anonimizado_en')
                ->whereMonth('fecha_nacimiento', now()->month)
                ->whereDay('fecha_nacimiento', now()->day)
                ->pluck('nombre')
                ->all(),
            'wartelisteHoy' => ListaEspera::withoutGlobalScopes()
                ->where('tenant_id', $tid)
                ->whereDate('fecha_deseada', today())
                ->where('estado', 'esperando')
                ->count(),
        ];
    }

    public function failed(Throwable $e): void
    {
        AuditLog::registrar('EMAIL_FALLO', 'Tenant', $this->tenant->id, null, [
            'tipo' => 'resumen',
            'error' => $e->getMessage(),
        ], 'error');
    }
}
