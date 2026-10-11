<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * OWASP A07 / CWE-613 — Insuficiente expiración de sesión
 * Verifica que las sesiones se invalidan al cambiar el rol o desactivar la cuenta.
 */
class UserObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_cambio_de_rol_invalida_sesiones(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'recepcionista']);

        // Insertar sesión ficticia
        DB::table('sessions')->insert([
            'id' => 'test-session-'.uniqid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $this->assertCount(1, DB::table('sessions')->where('user_id', $user->id)->get());

        // Cambiar el rol — debe invalidar sesiones
        $user->update(['rol' => 'admin']);

        $this->assertCount(0, DB::table('sessions')->where('user_id', $user->id)->get());
    }

    public function test_desactivar_cuenta_invalida_sesiones(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'activo' => true]);

        DB::table('sessions')->insert([
            'id' => 'test-session-'.uniqid(),
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'TestAgent',
            'payload' => base64_encode(serialize([])),
            'last_activity' => time(),
        ]);

        $user->update(['activo' => false]);

        $this->assertCount(0, DB::table('sessions')->where('user_id', $user->id)->get());
    }
}
