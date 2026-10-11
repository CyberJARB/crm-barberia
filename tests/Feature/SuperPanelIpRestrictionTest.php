<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zero Trust / OWASP A01 — el panel /super solo es accesible desde las IPs
 * de la allowlist del operador. La IP del test runner es 127.0.0.1.
 * (La app fuerza HTTPS, por eso las URLs se piden con esquema https.)
 */
class SuperPanelIpRestrictionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_allowlist_no_restringe(): void
    {
        config(['security.super_ip_allowlist' => []]);

        $this->get('https://localhost/super/login')->assertStatus(200);
    }

    public function test_ip_no_permitida_recibe_403(): void
    {
        config(['security.super_ip_allowlist' => ['10.0.0.1']]);

        $this->get('https://localhost/super/login')->assertStatus(403);
    }

    public function test_ip_permitida_accede(): void
    {
        config(['security.super_ip_allowlist' => ['127.0.0.1']]);

        $this->get('https://localhost/super/login')->assertStatus(200);
    }

    public function test_rango_cidr_permitido_accede(): void
    {
        config(['security.super_ip_allowlist' => ['127.0.0.0/24']]);

        $this->get('https://localhost/super/login')->assertStatus(200);
    }

    public function test_panel_admin_no_se_ve_afectado(): void
    {
        // La restricción es solo para /super; el panel de negocio /admin sigue abierto.
        config(['security.super_ip_allowlist' => ['10.0.0.1']]);

        $this->get('https://localhost/admin/login')->assertStatus(200);
    }
}
