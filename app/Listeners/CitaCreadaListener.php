<?php

namespace App\Listeners;

use App\Events\CitaCreada;
use App\Jobs\EnviarConfirmacionCita;
use App\Jobs\EnviarFollowUpCita;
use App\Jobs\EnviarRecordatorioCita;
use App\Jobs\EnviarRecordatorioMensaje;
use Carbon\Carbon;

class CitaCreadaListener
{
    public function handle(CitaCreada $event): void
    {
        $cita = $event->cita;
        $tenant = $cita->tenant;
        $tz = $tenant->zona_horaria ?: 'Europe/Berlin';

        // 1. Confirmación inmediata
        if ($tenant->email_confirmacion_activo) {
            EnviarConfirmacionCita::dispatch($cita);
        }

        // 2. Recordatorio: 24 h antes de la cita
        $horaInicio = Carbon::parse(
            $cita->fecha->format('Y-m-d').' '.$cita->hora_inicio,
            $tz
        );
        $enviarRecordatorioEn = $horaInicio->copy()->subDay();

        if ($enviarRecordatorioEn->isFuture()) {
            if ($tenant->email_recordatorio_activo) {
                EnviarRecordatorioCita::dispatch($cita)
                    ->delay($enviarRecordatorioEn);
            }

            // 2b. WhatsApp/SMS — solo con opt-in del cliente (§7 UWG) y
            // plan que lo incluya; el job revalida todo en el envío
            if ($tenant->mensajeria_activa && $tenant->permite('whatsapp') && $cita->cliente?->mensajeria_optin) {
                EnviarRecordatorioMensaje::dispatch($cita)
                    ->delay($enviarRecordatorioEn);
            }
        }

        // 3. Follow-up: hora_fin + email_followup_horas
        if ($tenant->email_followup_activo) {
            $horaFin = Carbon::parse(
                $cita->fecha->format('Y-m-d').' '.($cita->hora_fin ?? $cita->hora_inicio),
                $tz
            );
            $enviarFollowUpEn = $horaFin->addHours($tenant->email_followup_horas ?: 2);

            EnviarFollowUpCita::dispatch($cita)
                ->delay($enviarFollowUpEn);
        }
    }
}
