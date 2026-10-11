<?php

namespace Tests\Feature;

use App\Filament\Super\Resources\AuditLogGlobalResource;
use App\Filament\Super\Resources\TenantResource;
use App\Filament\Super\Widgets\TenantUsoWidget;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Funciones de operador del panel /super: detalle de negocio con uso real,
 * gestión de usuarios y auditoría global multi-tenant.
 */
class SuperPanelFuncionesTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superadmin = User::factory()->create([
            'tenant_id' => null,
            'rol' => 'superadmin',
        ]);
    }

    private function https(string $url): string
    {
        return str_replace('http://', 'https://', $url);
    }

    public function test_detalle_del_negocio_renderiza_con_kpis_de_uso(): void
    {
        $tenant = Tenant::factory()->create(['nombre' => 'Barber Gamma']);
        User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        $url = TenantResource::getUrl('view', ['record' => $tenant], panel: 'super');

        $this->actingAs($this->superadmin)
            ->get($this->https($url))
            ->assertOk()
            ->assertSee('Barber Gamma')
            ->assertSee('Benutzer');

        // Widget de uso (lazy) como componente Livewire
        Livewire::actingAs($this->superadmin)
            ->test(TenantUsoWidget::class, ['record' => $tenant])
            ->assertSee('Termine (30 Tage)')
            ->assertSee('Churn-Risiko')
            ->assertSee('Kunden gesamt');
    }

    public function test_audit_log_global_muestra_entradas_de_todos_los_tenants(): void
    {
        $tenantA = Tenant::factory()->create(['nombre' => 'Salon Alpha']);
        $tenantB = Tenant::factory()->create(['nombre' => 'Studio Beta']);

        AuditLog::create([
            'tenant_id' => $tenantA->id,
            'accion' => 'CITA_COBRADA',
            'entidad' => 'Pago',
            'resultado' => 'ok',
        ]);
        AuditLog::create([
            'tenant_id' => $tenantB->id,
            'accion' => 'GDPR_BORRADO',
            'entidad' => 'Cliente',
            'resultado' => 'ok',
        ]);

        $url = AuditLogGlobalResource::getUrl('index', panel: 'super');

        $this->actingAs($this->superadmin)
            ->get($this->https($url))
            ->assertOk()
            ->assertSee('Salon Alpha')
            ->assertSee('Studio Beta')
            ->assertSee('CITA_COBRADA')
            ->assertSee('GDPR_BORRADO');
    }

    public function test_usuario_de_negocio_no_ve_el_audit_global(): void
    {
        $tenant = Tenant::factory()->create();
        $propietario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        $url = AuditLogGlobalResource::getUrl('index', panel: 'super');

        $this->actingAs($propietario)->get($this->https($url))->assertForbidden();
    }
}
