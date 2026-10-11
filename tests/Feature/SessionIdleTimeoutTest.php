<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Zero Trust / OWASP A07 — la sesión se cierra tras inactividad y exige
 * re-login. (La app fuerza HTTPS; URLs con esquema https.)
 */
class SessionIdleTimeoutTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);
        app()->instance('current_tenant_id', $tenant->id);

        return $user;
    }

    public function test_sesion_inactiva_se_cierra(): void
    {
        config(['security.session.idle_timeout' => 30]);

        $this->actingAs($this->usuario())
            ->withSession(['last_activity' => now()->subMinutes(45)->timestamp])
            ->get('https://localhost/admin')
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_sesion_activa_continua(): void
    {
        config(['security.session.idle_timeout' => 30]);

        $this->actingAs($this->usuario())
            ->withSession(['last_activity' => now()->subMinutes(5)->timestamp])
            ->get('https://localhost/admin');

        $this->assertAuthenticated();
    }

    public function test_timeout_desactivado_no_cierra_sesion(): void
    {
        config(['security.session.idle_timeout' => 0]);

        $this->actingAs($this->usuario())
            ->withSession(['last_activity' => now()->subHours(10)->timestamp])
            ->get('https://localhost/admin');

        $this->assertAuthenticated();
    }
}
