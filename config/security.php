<?php

/**
 * Configuración de seguridad centralizada (OWASP A05 / ISO 27001 A.14 / NIST CSF Protect).
 * Un único lugar para ajustar políticas sin tocar código de middleware/providers.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (OWASP A07 / CWE-307 / CWE-770)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        // Login: intentos por ventana (complementa account lockout en User)
        'login_max_attempts' => (int) env('SEC_LOGIN_MAX_ATTEMPTS', 5),
        'login_decay_minutes' => (int) env('SEC_LOGIN_DECAY_MINUTES', 15),

        // Peticiones de lectura (GET/HEAD) por minuto por usuario/IP
        'web_reads_per_minute' => (int) env('SEC_WEB_READS_PER_MINUTE', 300),

        // Peticiones de escritura (POST/PUT/PATCH/DELETE incl. Livewire) por minuto
        'web_writes_per_minute' => (int) env('SEC_WEB_WRITES_PER_MINUTE', 180),

        // API autenticada por minuto
        'api_per_minute' => (int) env('SEC_API_PER_MINUTE', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Account lockout (OWASP A07 / CWE-307)
    |--------------------------------------------------------------------------
    */
    'lockout' => [
        'max_intentos' => (int) env('SEC_LOCKOUT_MAX_INTENTOS', 5),
        'minutos_bloqueo' => (int) env('SEC_LOCKOUT_MINUTOS', 15),
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Factor Authentication (OWASP A07 / NIST SP 800-63B AAL2)
    |--------------------------------------------------------------------------
    | Si está activo, todo usuario de los paneles debe configurar TOTP en el
    | primer acceso. Por defecto obligatorio en producción (exigido por las
    | Cyber-Versicherer), opcional en local/testing para no romper la suite.
    */
    'mfa' => [
        'required' => (bool) env('SEC_MFA_REQUIRED', env('APP_ENV') === 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | IP allowlist del panel del operador /super (Zero Trust / OWASP A01)
    |--------------------------------------------------------------------------
    | Lista separada por comas de IPs o rangos CIDR del operador. Vacía =
    | sin restricción. Ej.: SEC_SUPER_IP_ALLOWLIST="203.0.113.5,10.0.0.0/24"
    */
    'super_ip_allowlist' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('SEC_SUPER_IP_ALLOWLIST', ''))
    ))),

    /*
    |--------------------------------------------------------------------------
    | Cierre de sesión por inactividad (Zero Trust / OWASP A07)
    |--------------------------------------------------------------------------
    | Minutos sin actividad antes de exigir re-login. 0 = desactivado.
    | Por defecto 30 min en producción, 0 en local/testing.
    */
    'session' => [
        'idle_timeout' => (int) env('SEC_SESSION_IDLE_TIMEOUT', env('APP_ENV') === 'production' ? 30 : 0),
    ],

    /*
    |--------------------------------------------------------------------------
    | Política de contraseñas (NIST SP 800-63B)
    |--------------------------------------------------------------------------
    */
    'password' => [
        'min_length' => (int) env('SEC_PASSWORD_MIN_LENGTH', 12),
        'history_size' => (int) env('SEC_PASSWORD_HISTORY', 10),
        'check_pwned' => (bool) env('SEC_PASSWORD_CHECK_PWNED', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Content Security Policy (OWASP A03 / CWE-79)
    |--------------------------------------------------------------------------
    | 'unsafe-inline'/'unsafe-eval' en script-src son requeridos por
    | Livewire/Alpine (Filament). Si en el futuro se usa la build CSP de
    | Alpine, poner strict_scripts=true para eliminarlos.
    */
    'csp' => [
        'enabled' => (bool) env('SEC_CSP_ENABLED', true),
        'strict_scripts' => (bool) env('SEC_CSP_STRICT_SCRIPTS', false),
        // Orígenes externos adicionales permitidos (ej. reCAPTCHA)
        'extra_script_src' => array_filter(explode(',', (string) env('SEC_CSP_EXTRA_SCRIPT_SRC', 'https://www.google.com,https://www.gstatic.com'))),
        'extra_connect_src' => array_filter(explode(',', (string) env('SEC_CSP_EXTRA_CONNECT_SRC', 'https://www.google.com'))),
        'extra_frame_src' => array_filter(explode(',', (string) env('SEC_CSP_EXTRA_FRAME_SRC', 'https://www.google.com'))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Límites de input (CWE-1284 — validación de rangos)
    |--------------------------------------------------------------------------
    */
    'input' => [
        'max_money' => 99999.99,   // Importe máximo razonable por transacción (€)
        'max_notes_length' => 5000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Retención de logs (GDPR Art. 5(1)(e) / ISO 27001 A.12.4)
    |--------------------------------------------------------------------------
    */
    'retention' => [
        'security_log_days' => (int) env('SEC_LOG_RETENTION_DAYS', 90),
        'audit_log_days' => (int) env('SEC_AUDIT_RETENTION_DAYS', 365),
    ],
];
