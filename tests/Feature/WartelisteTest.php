<?php

namespace Tests\Feature;

use App\Filament\Resources\ListaEsperaResource;
use App\Models\Cliente;
use App\Models\ListaEspera;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Warteliste: conteo para avisos al liberar hueco, expiración
 * automática y aislamiento por tenant (OWASP A01).
 */
class WartelisteTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function entrada(array $extra = []): ListaEspera
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        return ListaEspera::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
        ], $extra));
    }

    public function test_esperando_para_cuenta_solo_fecha_tenant_y_estado_correctos(): void
    {
        $fecha = now()->addDays(3)->toDateString();

        $this->entrada(['fecha_deseada' => $fecha]);
        $this->entrada(['fecha_deseada' => $fecha, 'estado' => 'convertido']);
        $this->entrada(['fecha_deseada' => now()->addDays(5)->toDateString()]);

        // Otro tenant, misma fecha: no cuenta
        $otroTenant = Tenant::factory()->create();
        $clienteAjeno = Cliente::factory()->create(['tenant_id' => $otroTenant->id]);
        ListaEspera::factory()->create([
            'tenant_id' => $otroTenant->id,
            'cliente_id' => $clienteAjeno->id,
            'fecha_deseada' => $fecha,
        ]);

        $this->assertSame(1, ListaEspera::esperandoPara($this->tenant->id, $fecha));
    }

    public function test_comando_expira_entradas_pasadas_pero_no_futuras_ni_convertidas(): void
    {
        // Saltar la validación del form: las fechas pasadas se crean directo en BD
        $pasadaEsperando = $this->entrada(['fecha_deseada' => now()->subDays(2)->toDateString()]);
        $pasadaAvisada = $this->entrada(['fecha_deseada' => now()->subDay()->toDateString(), 'estado' => 'avisado']);
        $pasadaGanada = $this->entrada(['fecha_deseada' => now()->subDay()->toDateString(), 'estado' => 'convertido']);
        $futura = $this->entrada(['fecha_deseada' => now()->addDay()->toDateString()]);
        $deHoy = $this->entrada(['fecha_deseada' => now()->toDateString()]);

        $this->artisan('warteliste:expirar')
            ->expectsOutputToContain('2 entrada(s)')
            ->assertExitCode(0);

        $this->assertSame('expirado', $pasadaEsperando->fresh()->estado);
        $this->assertSame('expirado', $pasadaAvisada->fresh()->estado);
        $this->assertSame('convertido', $pasadaGanada->fresh()->estado);
        $this->assertSame('esperando', $futura->fresh()->estado);
        $this->assertSame('esperando', $deHoy->fresh()->estado);
    }

    public function test_la_lista_del_panel_renderiza_solo_entradas_del_tenant(): void
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario',
        ]);

        $cliente = Cliente::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Max Mustermann',
        ]);
        ListaEspera::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
        ]);

        $otroTenant = Tenant::factory()->create();
        $ajeno = Cliente::factory()->create([
            'tenant_id' => $otroTenant->id,
            'nombre' => 'Fremder Kunde',
        ]);
        ListaEspera::factory()->create([
            'tenant_id' => $otroTenant->id,
            'cliente_id' => $ajeno->id,
        ]);

        $url = str_replace(
            'http://',
            'https://',
            ListaEsperaResource::getUrl('index'),
        );

        $this->actingAs($user)
            ->get($url)
            ->assertOk()
            ->assertSee('Max Mustermann')
            ->assertDontSee('Fremder Kunde');
    }
}
