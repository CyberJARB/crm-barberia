<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Envío de mensajes transaccionales (WhatsApp/SMS).
 *
 * Cumplimiento UE/DE:
 * - Solo se llama tras verificar el opt-in del cliente (§7 UWG)
 * - El texto lo componen los jobs con minimización de datos (DSGVO Art. 5)
 * - Driver 'twilio' para producción (DPF + AVV), 'log' para dev/tests
 */
class Mensajeria
{
    /**
     * @throws \RuntimeException si el proveedor rechaza el envío
     */
    public function enviar(string $canal, string $telefono, string $texto): void
    {
        if (! in_array($canal, ['whatsapp', 'sms'], true)) {
            throw new \InvalidArgumentException("Canal inválido: {$canal}");
        }

        $destino = $this->normalizarTelefono($telefono);

        if ($destino === null) {
            throw new \RuntimeException('Telefonnummer ungültig oder nicht international formatierbar.');
        }

        match (config('mensajeria.driver')) {
            'twilio' => $this->enviarTwilio($canal, $destino, $texto),
            default => Log::info("[mensajeria:log] {$canal} → {$destino}: {$texto}"),
        };
    }

    private function enviarTwilio(string $canal, string $destino, string $texto): void
    {
        $sid = (string) config('mensajeria.twilio.sid');
        $token = (string) config('mensajeria.twilio.token');
        $desde = (string) config($canal === 'whatsapp'
            ? 'mensajeria.twilio.desde_whatsapp'
            : 'mensajeria.twilio.desde_sms');

        if ($sid === '' || $token === '' || $desde === '') {
            throw new \RuntimeException('Twilio no está configurado (TWILIO_SID/TOKEN/FROM).');
        }

        if ($canal === 'whatsapp') {
            $destino = 'whatsapp:'.$destino;
        }

        // Host fijo de Twilio — sin riesgo SSRF (no hay URL del usuario)
        $respuesta = Http::asForm()
            ->withBasicAuth($sid, $token)
            ->timeout(15)
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                'To' => $destino,
                'From' => $desde,
                'Body' => $texto,
            ]);

        if (! $respuesta->successful()) {
            // CWE-209 — el detalle va al log, no al usuario
            throw new \RuntimeException('Twilio rechazó el mensaje: HTTP '.$respuesta->status());
        }
    }

    /**
     * Normaliza a E.164. Números alemanes locales (0...) → +49.
     * Devuelve null si no parece un número internacionalizable.
     */
    public function normalizarTelefono(string $telefono): ?string
    {
        $limpio = preg_replace('/[\s\-()\/]+/', '', trim($telefono)) ?? '';

        if (str_starts_with($limpio, '00')) {
            $limpio = '+'.substr($limpio, 2);
        } elseif (str_starts_with($limpio, '0')) {
            // Convención local alemana (mercado objetivo del SaaS)
            $limpio = '+49'.substr($limpio, 1);
        }

        return preg_match('/^\+[1-9]\d{6,14}$/', $limpio) === 1 ? $limpio : null;
    }
}
