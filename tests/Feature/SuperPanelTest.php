<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel del operador (/super): separación estricta de paneles (OWASP A01).
 * El superadmin gestiona todos los tenants; los usuarios de negocio no
 * pueden entrar a /super y el superadmin no puede entrar a /admin.
 */
class SuperPanelTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        return User::factory()->create([
            'tenant_id' => null,
            'rol' => 'superadmin',
            'activo' => true,
        ]);
    }

    public function test_superadmin_ve_el_panel_con_todos_los_tenants(): void
    {
        Tenant::factory()->create(['nombre' => 'Barber Alpha']);
        Tenant::factory()->create(['nombre' => 'Nagelstudio Beta']);

        $url = str_replace('http://', 'https://', route('filament.super.resources.tenants.index'));

        $this->actingAs($this->superadmin())
            ->get($url)
            ->assertOk()
            ->assertSee('Barber Alpha')
            ->assertSee('Nagelstudio Beta');
    }

    public function test_usuario_de_negocio_no_entra_al_panel_super(): void
    {
        $tenant = Tenant::factory()->create();
        $propietario = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);

        $url = str_replace('http://', 'https://', route('filament.super.pages.dashboard'));

        $this->actingAs($propietario)->get($url)->assertForbidden();
    }

    public function test_superadmin_no_entra_al_panel_de_los_negocios(): void
    {
        $url = str_replace('http://', 'https://', route('filament.admin.pages.dashboard'));

        $this->actingAs($this->superadmin())->get($url)->assertForbidden();
    }

    public function test_invitado_es_redirigido_al_login_del_super(): void
    {
        $url = str_replace('http://', 'https://', route('filament.super.pages.dashboard'));

        $this->get($url)->assertRedirect(route('filament.super.auth.login'));
    }

    public function test_superadmin_inactivo_no_entra(): void
    {
        $inactivo = User::factory()->create([
            'tenant_id' => null,
            'rol' => 'superadmin',
            'activo' => false,
        ]);

        $url = str_replace('http://', 'https://', route('filament.super.pages.dashboard'));

        $this->actingAs($inactivo)->get($url)->assertForbidden();
    }

    public function test_es_superadmin_exige_rol_y_ausencia_de_tenant(): void
    {
        $this->assertTrue($this->superadmin()->esSuperAdmin());

        // Un usuario de tenant con rol manipulado NO cuenta como superadmin
        $tenant = Tenant::factory()->create();
        $raro = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'superadmin']);

        $this->assertFalse($raro->esSuperAdmin());
    }
}
