<?php

namespace Tests\Unit;

use App\Support\SsrfGuard;
use Tests\TestCase;

/**
 * OWASP A10 / CWE-918 — SSRF Protection
 * Verifica que SsrfGuard bloquea rangos IP privados y hosts de metadatos.
 */
class SsrfGuardTest extends TestCase
{
    public function test_bloquea_loopback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('http://127.0.0.1/secret');
    }

    public function test_bloquea_rango_privado_192(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('http://192.168.1.1/admin');
    }

    public function test_bloquea_rango_privado_10(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('http://10.0.0.1/internal');
    }

    public function test_bloquea_metadata_aws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('http://169.254.169.254/latest/meta-data/');
    }

    public function test_bloquea_esquema_file(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('file:///etc/passwd');
    }

    public function test_bloquea_esquema_gopher(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('gopher://attacker.com:70/_SSRF');
    }

    public function test_url_invalida_lanza_excepcion(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        SsrfGuard::validar('no-es-una-url');
    }
}
