<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Personal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Página Kalender: renderiza para usuarios del tenant y solo
 * muestra citas del propio tenant (OWASP A01).
 */
class KalenderPageTest extends TestCase
{
    use RefreshDatabase;

    private function urlKalender(): string
    {
        return str_replace('http://', 'https://', route('filament.admin.pages.kalender'));
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get($this->urlKalender())
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_usuario_ve_el_kalender_con_sus_citas(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        app()->instance('current_tenant_id', $tenant->id);

        $personal = Personal::factory()->create(['tenant_id' => $tenant->id]);
        $cliente = Cliente::factory()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Max Mustermann',
        ]);

        Cita::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'personal_id' => $personal->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($user)
            ->get($this->urlKalender())
            ->assertOk()
            ->assertSee('Kalender')
            ->assertSee('Max Mustermann');
    }

    public function test_no_se_muestran_citas_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => 'propietario']);

        $clienteB = Cliente::factory()->create([
            'tenant_id' => $tenantB->id,
            'nombre' => 'Fremder Kunde',
        ]);

        Cita::factory()->create([
            'tenant_id' => $tenantB->id,
            'cliente_id' => $clienteB->id,
            'personal_id' => Personal::factory()->create(['tenant_id' => $tenantB->id])->id,
            'fecha' => now()->toDateString(),
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'confirmada',
        ]);

        $this->actingAs($user)
            ->get($this->urlKalender())
            ->assertOk()
            ->assertDontSee('Fremder Kunde');
    }
}
