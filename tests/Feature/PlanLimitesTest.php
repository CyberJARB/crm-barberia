<?php

namespace Tests\Feature;

use App\Models\Personal;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\PersonalPolicy;
use App\Support\Planes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Gating por plan (OWASP A04): Basis 29€ / Professionell 59€ / Premium 99€.
 * Los límites se aplican server-side (policy, jobs, middleware, controller)
 * — la UI nunca es la única barrera.
 */
class PlanLimitesTest extends TestCase
{
    use RefreshDatabase;

    private function tenant(string $plan, bool $trial = false): Tenant
    {
        return Tenant::factory()->create([
            'plan' => $plan,
            'trial_ends_at' => $trial ? now()->addDays(10) : null,
        ]);
    }

    // === Matriz de features ===========================================

    public function test_matriz_de_features_por_plan(): void
    {
        $basico = $this->tenant('basico');
        $this->assertFalse($basico->permite('whatsapp'));
        $this->assertFalse($basico->permite('berichte'));
        $this->assertFalse($basico->permite('api'));
        $this->assertSame(3, $basico->limitePersonal());

        $profesional = $this->tenant('profesional');
        $this->assertTrue($profesional->permite('whatsapp'));
        $this->assertTrue($profesional->permite('berichte'));
        $this->assertFalse($profesional->permite('api'));
        $this->assertSame(10, $profesional->limitePersonal());

        $premium = $this->tenant('premium');
        $this->assertTrue($premium->permite('api'));
        $this->assertNull($premium->limitePersonal());
    }

    public function test_durante_el_trial_se_prueba_el_producto_completo(): void
    {
        $enTrial = $this->tenant('basico', trial: true);

        $this->assertSame('premium', $enTrial->planEfectivo());
        $this->assertTrue($enTrial->permite('whatsapp'));
        $this->assertTrue($enTrial->permite('api'));
        $this->assertNull($enTrial->limitePersonal());
    }

    public function test_plan_desconocido_cae_al_mas_restrictivo(): void
    {
        // Fail-closed (OWASP A04)
        $this->assertSame(Planes::limites('basico'), Planes::limites('plan-inventado'));
    }

    // === Límite de empleados ==========================================

    public function test_basis_no_puede_crear_el_cuarto_empleado(): void
    {
        $tenant = $this->tenant('basico');
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        Personal::factory()->count(3)->create(['tenant_id' => $tenant->id]);

        $this->assertFalse($tenant->puedeCrearPersonal());
        $this->assertFalse((new PersonalPolicy)->create($admin));
    }

    public function test_basis_con_dos_empleados_si_puede_crear(): void
    {
        $tenant = $this->tenant('basico');
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        Personal::factory()->count(2)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($tenant->puedeCrearPersonal());
        $this->assertTrue((new PersonalPolicy)->create($admin));
    }

    public function test_premium_no_tiene_limite_de_empleados(): void
    {
        $tenant = $this->tenant('premium');

        Personal::factory()->count(15)->create(['tenant_id' => $tenant->id]);

        $this->assertTrue($tenant->puedeCrearPersonal());
    }

    // === API solo Premium =============================================

    public function test_api_devuelve_403_para_plan_basis(): void
    {
        $tenant = $this->tenant('basico');
        $key = 'pk_test_'.Str::random(32);
        $tenant->forceFill(['api_key_hash' => hash('sha256', $key)])->save();

        $this->getJson('https://localhost/api/v1/servicios', ['X-API-Key' => $key])
            ->assertForbidden()
            ->assertJsonPath('code', 'PLAN_ERFORDERLICH');
    }

    public function test_api_funciona_para_premium(): void
    {
        $tenant = $this->tenant('premium');
        $key = 'pk_test_'.Str::random(32);
        $tenant->forceFill(['api_key_hash' => hash('sha256', $key)])->save();

        $this->getJson('https://localhost/api/v1/servicios', ['X-API-Key' => $key])
            ->assertOk();
    }

    // === Berichte desde Professionell ==================================

    public function test_berichte_bloqueados_para_basis_incluso_por_url_directa(): void
    {
        $tenant = $this->tenant('basico');
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        app()->instance('current_tenant_id', $tenant->id);

        $pagina = str_replace('http://', 'https://', route('filament.admin.pages.berichte'));
        $this->actingAs($admin)->get($pagina)->assertForbidden();

        // Defensa en profundidad: la URL de descarga directa también niega
        $descarga = str_replace('http://', 'https://', route('reportes.descargar', [
            'tipo_reporte' => 'clientes',
            'formato' => 'pdf',
        ]));
        $this->actingAs($admin)->get($descarga)->assertForbidden();
    }

    public function test_berichte_accesibles_para_professionell(): void
    {
        $tenant = $this->tenant('profesional');
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        app()->instance('current_tenant_id', $tenant->id);

        $pagina = str_replace('http://', 'https://', route('filament.admin.pages.berichte'));
        $this->actingAs($admin)->get($pagina)->assertOk();
    }
}
