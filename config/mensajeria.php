<?php

/**
 * Mensajería transaccional (WhatsApp/SMS) — recordatorios de cita.
 *
 * Drivers:
 * - 'log'    → escribe en el log (desarrollo/tests; no sale ningún mensaje)
 * - 'twilio' → Twilio Messaging API (producción). Twilio Inc. está
 *              certificado en el EU-US Data Privacy Framework y ofrece
 *              AVV/DPA (DSGVO Art. 28) — firmarlo antes de producción.
 */
return [
    'driver' => env('MENSAJERIA_DRIVER', 'log'),

    'twilio' => [
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        // Número emisor SMS (E.164, p. ej. +4915735980000)
        'desde_sms' => env('TWILIO_FROM_SMS'),
        // Emisor WhatsApp aprobado por Meta (formato whatsapp:+49...)
        'desde_whatsapp' => env('TWILIO_FROM_WHATSAPP'),
    ],
];
