<?php

namespace Tests\Feature;

use App\Filament\Resources\ClienteResource;
use App\Filament\Resources\ClienteResource\Widgets\ClienteStatsWidget;
use App\Filament\Resources\ValeResource;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Perfil de cliente (ViewCliente) y recurso Gutscheine en el panel:
 * renderizado, KPIs y aislamiento por tenant (OWASP A01).
 */
class KundenprofilTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function https(string $url): string
    {
        return str_replace('http://', 'https://', $url);
    }

    public function test_perfil_del_cliente_renderiza_con_kpis(): void
    {
        $cliente = Cliente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Max Mustermann',
            'total_visitas' => 7,
        ]);

        $url = ClienteResource::getUrl('view', ['record' => $cliente]);

        $this->actingAs($this->user)
            ->get($this->https($url))
            ->assertOk()
            ->assertSee('Max Mustermann');

        // El widget de KPIs es lazy: se testea como componente Livewire
        Livewire::actingAs($this->user)
            ->test(ClienteStatsWidget::class, ['record' => $cliente])
            ->assertSee('Besuche')
            ->assertSee('7')
            ->assertSee('Umsatz gesamt')
            ->assertSee('Zuverlässigkeit');
    }

    public function test_perfil_de_cliente_de_otro_tenant_devuelve_404(): void
    {
        $otroTenant = Tenant::factory()->create();
        $ajeno = Cliente::factory()->create(['tenant_id' => $otroTenant->id]);

        $url = ClienteResource::getUrl('view', ['record' => $ajeno->id]);

        $this->actingAs($this->user)
            ->get($this->https($url))
            ->assertNotFound();
    }

    public function test_lista_de_gutscheine_renderiza_solo_los_del_tenant(): void
    {
        Vale::factory()->create(['tenant_id' => $this->tenant->id, 'codigo' => 'GS-AAAA-BBBB']);

        $otroTenant = Tenant::factory()->create();
        Vale::factory()->create(['tenant_id' => $otroTenant->id, 'codigo' => 'GS-ZZZZ-YYYY']);

        $url = ValeResource::getUrl('index');

        $this->actingAs($this->user)
            ->get($this->https($url))
            ->assertOk()
            ->assertSee('GS-AAAA-BBBB')
            ->assertDontSee('GS-ZZZZ-YYYY');
    }
}
