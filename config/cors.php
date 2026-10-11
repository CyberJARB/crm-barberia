<?php

/**
 * OWASP A05 — Security Misconfiguration / CWE-942
 * CORS restringido: solo el dominio propio en producción.
 * En local se permite localhost para desarrollo.
 */
return [
    // Solo rutas API expuestas a orígenes externos
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // En producción: APP_URL + los dominios de las webs de los tenants que
    // consumen la API pública de reservas (CORS_TENANT_ORIGINS, separados
    // por comas, p. ej. "https://puissance-hamburg.de,https://otro.de").
    'allowed_origins' => array_merge(
        env('APP_ENV') === 'production'
            ? [env('APP_URL')]
            : ['http://localhost:3000', 'http://127.0.0.1:3000', 'http://localhost:8000', 'http://localhost:8765', 'http://127.0.0.1:8765'],
        array_filter(array_map('trim', explode(',', (string) env('CORS_TENANT_ORIGINS', '')))),
    ),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'X-XSRF-TOKEN', 'X-API-Key'],

    'exposed_headers' => [],

    'max_age' => 86400,  // 24h preflight cache

    // true solo si se necesitan cookies cross-origin (evitar si es posible)
    'supports_credentials' => false,
];
