<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * OWASP A07 / CWE-307 — Brute Force Protection
 * Verifica bloqueo de cuenta tras intentos fallidos.
 */
class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('Test#Clave2026!'),
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ]);
    }

    public function test_cuenta_no_bloqueada_inicialmente(): void
    {
        $this->assertFalse($this->user->estaBloqueado());
    }

    public function test_bloqueo_tras_cinco_intentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->user->registrarIntentoFallido();
        }

        $this->assertTrue($this->user->fresh()->estaBloqueado());
    }

    public function test_login_exitoso_resetea_contador(): void
    {
        $this->user->update(['intentos_fallidos' => 3]);
        $this->user->registrarLoginExitoso();

        $this->assertEquals(0, $this->user->fresh()->intentos_fallidos);
        $this->assertNull($this->user->fresh()->bloqueado_hasta);
    }

    public function test_minutos_para_desbloqueo_mayor_que_cero(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->user->registrarIntentoFallido();
        }

        $this->assertGreaterThan(0, $this->user->fresh()->minutosParaDesbloqueo());
    }

    public function test_bloqueo_expira_tras_quince_minutos(): void
    {
        $this->user->update([
            'intentos_fallidos' => 5,
            'bloqueado_hasta' => now()->subMinutes(1),
        ]);

        $this->assertFalse($this->user->fresh()->estaBloqueado());
    }
}
