<?php

/**
 * Identidad del SaaS — única fuente de verdad para marca y soporte.
 * El producto es marca blanca de cara a los tenants; CyberJARB es el
 * operador (Betreiber) que aparece en el panel /super y en el footer.
 */
return [
    'producto' => env('SAAS_PRODUCTO', 'Salon CRM'),
    'empresa' => env('SAAS_EMPRESA', 'CyberJARB'),
    'web' => env('SAAS_WEB', 'https://cyberjarb.de'),

    'soporte' => [
        'email' => env('SAAS_SOPORTE_EMAIL', 'kontakt@cyberjarb.de'),
        'whatsapp' => env('SAAS_SOPORTE_WHATSAPP', ''),
    ],
];
