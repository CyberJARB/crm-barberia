<?php

namespace Tests\Feature;

use App\Filament\Pages\PipelinePage;
use App\Jobs\EnviarAcuseLead;
use App\Models\Cliente;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Leads;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Embudo de leads: máquina de estados (CWE-841), conversión a cliente,
 * ingesta API con gating de auto-respuesta (§7 UWG + plan) y aislamiento
 * por tenant (OWASP A01).
 */
class LeadPipelineTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['activo' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function lead(array $extra = []): Lead
    {
        return Lead::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'estado' => 'nuevo',
        ], $extra));
    }

    // === Máquina de estados ===

    public function test_transicion_valida_avanza_y_marca_timestamps(): void
    {
        $lead = $this->lead();

        app(Leads::class)->transicionar($lead, 'contactado');

        $this->assertSame('contactado', $lead->fresh()->estado);
        $this->assertNotNull($lead->fresh()->contactado_en);
    }

    public function test_transicion_invalida_lanza_excepcion(): void
    {
        $lead = $this->lead(['estado' => 'nuevo']);

        $this->expectException(\DomainException::class);
        app(Leads::class)->transicionar($lead, 'ganado'); // nuevo no salta directo a ganado
    }

    public function test_perdido_exige_motivo_y_marca_cierre(): void
    {
        $lead = $this->lead(['estado' => 'contactado']);

        app(Leads::class)->transicionar($lead, 'perdido', 'precio');

        $fresco = $lead->fresh();
        $this->assertSame('perdido', $fresco->estado);
        $this->assertSame('precio', $fresco->motivo_perdida);
        $this->assertNotNull($fresco->cerrado_en);
    }

    // === Conversión a cliente ===

    public function test_convertir_crea_cliente_y_es_idempotente(): void
    {
        $lead = $this->lead([
            'estado' => 'cualificado',
            'email' => 'neu@kunde.de',
            'nombre' => 'Neue Kundin',
        ]);
        app(Leads::class)->transicionar($lead, 'ganado');

        $service = app(Leads::class);
        $cliente1 = $service->convertir($lead->fresh());
        $cliente2 = $service->convertir($lead->fresh());

        $this->assertSame($cliente1->id, $cliente2->id);
        $this->assertSame(1, Cliente::where('email', 'neu@kunde.de')->count());
        $this->assertSame($cliente1->id, $lead->fresh()->cliente_id);
    }

    public function test_convertir_enlaza_cliente_existente_por_email(): void
    {
        $existente = Cliente::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Stammkunde',
            'email' => 'stamm@kunde.de',
            'activo' => true,
        ]);

        $lead = $this->lead(['estado' => 'propuesta', 'email' => 'stamm@kunde.de']);
        app(Leads::class)->transicionar($lead, 'ganado');

        $cliente = app(Leads::class)->convertir($lead->fresh());

        $this->assertSame($existente->id, $cliente->id);
        $this->assertSame(1, Cliente::where('email', 'stamm@kunde.de')->count());
    }

    // === Ingesta API + gating de auto-respuesta ===

    private function apiKeyPara(Tenant $tenant): string
    {
        $key = 'pk_test_'.Str::random(32);
        $tenant->forceFill(['api_key_hash' => hash('sha256', $key)])->save();

        return $key;
    }

    public function test_api_crea_lead_en_estado_nuevo(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true, 'plan' => 'premium']);
        $key = $this->apiKeyPara($tenant);

        $this->postJson('https://localhost/api/v1/leads', [
            'nombre' => 'Web Interessent',
            'email' => 'web@interessent.de',
            'fuente' => 'web',
        ], ['X-API-Key' => $key])
            ->assertStatus(201)
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('leads', [
            'tenant_id' => $tenant->id,
            'email' => 'web@interessent.de',
            'estado' => 'nuevo',
            'fuente' => 'web',
        ]);
    }

    public function test_api_dispara_acuse_solo_con_optin_y_plan_whatsapp(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create([
            'activo' => true, 'plan' => 'premium', 'mensajeria_activa' => true,
        ]);
        $key = $this->apiKeyPara($tenant);

        $this->postJson('https://localhost/api/v1/leads', [
            'nombre' => 'Mit Einwilligung',
            'telefono' => '+49 170 1234567',
            'optin' => true,
        ], ['X-API-Key' => $key])->assertStatus(201);

        Queue::assertPushed(EnviarAcuseLead::class);
    }

    public function test_api_sin_optin_no_dispara_acuse(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create([
            'activo' => true, 'plan' => 'premium', 'mensajeria_activa' => true,
        ]);
        $key = $this->apiKeyPara($tenant);

        $this->postJson('https://localhost/api/v1/leads', [
            'nombre' => 'Ohne Einwilligung',
            'telefono' => '+49 170 1234567',
            'optin' => false,
        ], ['X-API-Key' => $key])->assertStatus(201);

        Queue::assertNotPushed(EnviarAcuseLead::class);
    }

    public function test_api_de_plan_no_premium_devuelve_403(): void
    {
        $tenant = Tenant::factory()->create(['activo' => true, 'plan' => 'profesional']);
        $key = $this->apiKeyPara($tenant);

        $this->postJson('https://localhost/api/v1/leads', [
            'nombre' => 'X',
        ], ['X-API-Key' => $key])->assertStatus(403);
    }

    // === Aislamiento por tenant (OWASP A01) ===

    public function test_pipeline_solo_muestra_leads_del_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario',
        ]);

        $this->lead(['nombre' => 'Eigener Lead']);

        $otro = Tenant::factory()->create();
        Lead::factory()->create(['tenant_id' => $otro->id, 'nombre' => 'Fremder Lead']);

        $this->actingAs($user);

        Livewire::test(PipelinePage::class)
            ->assertSee('Eigener Lead')
            ->assertDontSee('Fremder Lead');
    }

    public function test_readonly_no_puede_actualizar_lead(): void
    {
        $readonly = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'readonly',
        ]);
        $lead = $this->lead();

        $this->assertFalse($readonly->can('update', $lead));
    }
}
