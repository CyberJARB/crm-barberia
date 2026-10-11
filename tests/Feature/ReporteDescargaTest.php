<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * OWASP A01 / CWE-639 — Control de acceso en la descarga de reportes.
 * CWE-1284 — Validación del rango de fechas (máx. 366 días).
 */
class ReporteDescargaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * URL https — ForceHttps (OWASP A02) emite 301 para peticiones
     * en claro también en el entorno de tests.
     */
    private function urlReporte(array $params = []): string
    {
        return str_replace('http://', 'https://', route('reportes.descargar', array_merge([
            'tipo_reporte' => 'ingresos',
            'fecha_inicio' => now()->startOfMonth()->toDateString(),
            'fecha_hasta' => now()->toDateString(),
            'formato' => 'pdf',
        ], $params)));
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get($this->urlReporte())
            ->assertRedirect(route('filament.admin.auth.login'));
    }

    public function test_recepcionista_no_puede_descargar_reportes(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profesional']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'recepcionista',
        ]);

        $this->actingAs($user)
            ->get($this->urlReporte())
            ->assertForbidden();
    }

    public function test_admin_descarga_reporte_pdf(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profesional']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);

        $response = $this->actingAs($user)->get($this->urlReporte());

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString(
            'attachment',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_admin_descarga_reporte_excel(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profesional']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);

        $response = $this->actingAs($user)->get($this->urlReporte(['formato' => 'excel']));

        $response->assertOk();
        $this->assertStringContainsString(
            '.xlsx',
            (string) $response->headers->get('content-disposition')
        );
    }

    public function test_rango_mayor_a_366_dias_es_rechazado(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profesional']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);

        $this->actingAs($user)
            ->get($this->urlReporte([
                'fecha_inicio' => now()->subDays(400)->toDateString(),
                'fecha_hasta' => now()->toDateString(),
            ]))
            ->assertStatus(422);
    }

    public function test_tipo_de_reporte_invalido_es_rechazado(): void
    {
        $tenant = Tenant::factory()->create(['plan' => 'profesional']);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'rol' => 'propietario',
        ]);

        $this->actingAs($user)
            ->get($this->urlReporte(['tipo_reporte' => 'usuarios']))
            ->assertSessionHasErrors('tipo_reporte');
    }
}
