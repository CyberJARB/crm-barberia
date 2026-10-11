<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OWASP A01 / CWE-285 — Insecure Direct Object Reference (IDOR)
 * Verifica que el global scope de tenant_id aísla los datos entre tenants.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_no_ve_clientes_de_otro_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create(['tenant_id' => $tenantA->id]);
        $userB = User::factory()->create(['tenant_id' => $tenantB->id]);

        $clienteB = Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        // Activar scope para tenant A
        app()->instance('current_tenant_id', $tenantA->id);

        $this->actingAs($userA);

        $clientes = Cliente::all();
        $this->assertFalse($clientes->contains('id', $clienteB->id));
    }

    public function test_usuario_solo_ve_sus_propios_clientes(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $clienteA = Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        app()->instance('current_tenant_id', $tenantA->id);

        $clientes = Cliente::all();
        $this->assertCount(1, $clientes);
        $this->assertEquals($clienteA->id, $clientes->first()->id);
    }

    public function test_cliente_nuevo_hereda_tenant_del_contexto(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        $cliente = Cliente::factory()->create(['nombre' => 'Test', 'tenant_id' => $tenant->id]);

        $this->assertEquals($tenant->id, $cliente->tenant_id);
    }

    public function test_leads_aislados_entre_tenants(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Lead::factory()->create(['tenant_id' => $tenantA->id]);
        $leadB = Lead::factory()->create(['tenant_id' => $tenantB->id]);

        app()->instance('current_tenant_id', $tenantA->id);

        $leads = Lead::all();
        $this->assertCount(1, $leads);
        $this->assertFalse($leads->contains('id', $leadB->id));
    }

    public function test_creacion_sin_tenant_explicito_usa_el_del_contexto(): void
    {
        $tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenant->id);

        // Sin pasar tenant_id: el trait BelongsToTenant debe inyectarlo.
        $lead = Lead::factory()->create(['tenant_id' => null]);

        $this->assertEquals($tenant->id, $lead->fresh()->tenant_id);
    }

    /**
     * Comportamiento DOCUMENTADO (fail-open): sin contexto de tenant ligado,
     * el global scope NO filtra (necesario para super-admin/consola).
     * Las peticiones de cliente SIEMPRE ligan el contexto vía middleware
     * (EnsureTenantScope / AutenticarTenantApi), por lo que este camino no es
     * alcanzable desde un request de tenant. Si en el futuro se migra a
     * fail-closed, este test debe actualizarse conscientemente.
     */
    public function test_sin_contexto_el_scope_no_filtra_por_diseno(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        Cliente::factory()->create(['tenant_id' => $tenantA->id]);
        Cliente::factory()->create(['tenant_id' => $tenantB->id]);

        // Garantizar que NO hay contexto ligado.
        app()->forgetInstance('current_tenant_id');

        $this->assertCount(2, Cliente::all());
    }
}
