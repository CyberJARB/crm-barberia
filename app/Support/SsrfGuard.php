<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;

/**
 * OWASP A10 — Server-Side Request Forgery (SSRF)
 * CWE-918 — Server-Side Request Forgery
 * Valida URLs antes de realizar peticiones HTTP externas.
 * Bloquea rangos privados, loopback y metadatos de cloud.
 */
class SsrfGuard
{
    /** Rangos IP privados / reservados bloqueados (RFC 1918 + RFC 4193) */
    private const BLOQUEADOS_CIDR = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '::1/128',
        'fc00::/7',
        '169.254.0.0/16',   // Link-local / AWS metadata 169.254.169.254
        '100.64.0.0/10',    // CGNAT
        '0.0.0.0/8',
    ];

    /** Hosts conocidos de metadatos de cloud — SSRF clásico */
    private const HOSTS_BLOQUEADOS = [
        '169.254.169.254',   // AWS / GCP / Azure IMDS
        'metadata.google.internal',
        'metadata.internal',
        'instance-data',
    ];

    /**
     * Valida que una URL es segura para realizar una petición HTTP saliente.
     *
     * @throws \InvalidArgumentException si la URL es peligrosa
     */
    public static function validar(string $url): void
    {
        $parsed = parse_url($url);

        if (! $parsed || empty($parsed['host'])) {
            throw new \InvalidArgumentException('URL inválida.');
        }

        $scheme = strtolower($parsed['scheme'] ?? '');

        // Solo permitir http/https — bloquear file://, gopher://, etc.
        if (! in_array($scheme, ['http', 'https'], true)) {
            Log::channel('security')->warning('SSRF_BLOCKED', ['url' => $url, 'motivo' => 'esquema no permitido']);
            throw new \InvalidArgumentException("Esquema no permitido: {$scheme}");
        }

        $host = strtolower($parsed['host']);

        // Bloquear hosts de metadatos cloud
        if (in_array($host, self::HOSTS_BLOQUEADOS, true)) {
            Log::channel('security')->warning('SSRF_BLOCKED', ['url' => $url, 'motivo' => 'host de metadatos cloud']);
            throw new \InvalidArgumentException("Host bloqueado: {$host}");
        }

        // Resolver DNS y validar IP resultante
        $ip = gethostbyname($host);

        if ($ip === $host && ! filter_var($ip, FILTER_VALIDATE_IP)) {
            // No se pudo resolver — rechazar por precaución
            throw new \InvalidArgumentException("No se pudo resolver el host: {$host}");
        }

        foreach (self::BLOQUEADOS_CIDR as $cidr) {
            if (self::ipEnCidr($ip, $cidr)) {
                Log::channel('security')->warning('SSRF_BLOCKED', [
                    'url' => $url,
                    'ip' => $ip,
                    'cidr' => $cidr,
                    'motivo' => 'IP en rango privado/reservado',
                ]);
                throw new \InvalidArgumentException("IP destino bloqueada (rango privado): {$ip}");
            }
        }
    }

    private static function ipEnCidr(string $ip, string $cidr): bool
    {
        [$red, $bits] = explode('/', $cidr);

        // Solo comparación IPv4 por ahora
        if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        if (! filter_var($red, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }

        $ipLong = ip2long($ip);
        $redLong = ip2long($red);
        $mascara = -1 << (32 - (int) $bits);

        return ($ipLong & $mascara) === ($redLong & $mascara);
    }
}
