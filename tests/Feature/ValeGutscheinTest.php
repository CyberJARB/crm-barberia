<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\Vale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gutscheine (vales regalo): generación segura de códigos (CWE-338),
 * canje atómico con lock (CWE-362), aislamiento por tenant (OWASP A01)
 * y trazabilidad de auditoría (OWASP A09).
 */
class ValeGutscheinTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function crearCita(array $atributos = []): Cita
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        return Cita::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'estado' => 'confirmada',
        ], $atributos));
    }

    // === Emisión =======================================================

    public function test_al_crear_genera_codigo_unico_y_saldo_igual_al_valor(): void
    {
        $vale = Vale::create(['tenant_id' => $this->tenant->id, 'valor_inicial' => 50]);

        $this->assertMatchesRegularExpression('/^GS-[A-Z2-9]{4}-[A-Z2-9]{4}$/', $vale->codigo);
        $this->assertSame('50.00', (string) $vale->saldo);
        $this->assertTrue($vale->esCanjeable());
    }

    public function test_codigo_sin_caracteres_ambiguos(): void
    {
        $vale = Vale::create(['tenant_id' => $this->tenant->id, 'valor_inicial' => 25]);

        $this->assertDoesNotMatchRegularExpression('/[0O1IL]/', substr($vale->codigo, 3));
    }

    // === Estados =======================================================

    public function test_vale_caducado_anulado_o_agotado_no_es_canjeable(): void
    {
        $caducado = Vale::factory()->caducado()->create(['tenant_id' => $this->tenant->id]);
        $anulado = Vale::factory()->anulado()->create(['tenant_id' => $this->tenant->id]);
        $agotado = Vale::factory()->agotado()->create(['tenant_id' => $this->tenant->id]);

        $this->assertFalse($caducado->esCanjeable());
        $this->assertSame('caducado', $caducado->estado());

        $this->assertFalse($anulado->esCanjeable());
        $this->assertSame('anulado', $anulado->estado());

        $this->assertFalse($agotado->esCanjeable());
        $this->assertSame('agotado', $agotado->estado());
    }

    public function test_vale_valido_hasta_hoy_sigue_siendo_canjeable(): void
    {
        $vale = Vale::factory()->create([
            'tenant_id' => $this->tenant->id,
            'valido_hasta' => today(),
        ]);

        $this->assertTrue($vale->esCanjeable());
    }

    public function test_anular_es_auditado(): void
    {
        $vale = Vale::factory()->create(['tenant_id' => $this->tenant->id]);

        $vale->anular();

        $this->assertTrue($vale->fresh()->estaAnulado());
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'VALE_ANULADO',
            'entidad' => 'Vale',
            'entidad_id' => $vale->id,
        ]);
    }

    // === Canje en el cobro =============================================

    public function test_vale_que_cubre_el_total_crea_un_solo_pago(): void
    {
        $cita = $this->crearCita(['total' => 40]);
        $vale = Vale::factory()->create(['tenant_id' => $this->tenant->id, 'valor_inicial' => 50, 'saldo' => 50]);

        $cita->cobrar('efectivo', null, $vale);

        $this->assertSame('completada', $cita->fresh()->estado);
        $this->assertSame(1, Pago::count());

        $pago = Pago::sole();
        $this->assertSame('vale', $pago->metodo);
        $this->assertSame('40.00', (string) $pago->monto);
        $this->assertSame($vale->id, $pago->vale_id);
        $this->assertSame($vale->codigo, $pago->referencia);

        // Saldo restante: 50 - 40 = 10
        $this->assertSame('10.00', (string) $vale->fresh()->saldo);

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'VALE_CANJEADO',
            'entidad' => 'Vale',
            'entidad_id' => $vale->id,
        ]);
    }

    public function test_canje_parcial_crea_pago_vale_mas_pago_resto(): void
    {
        $cita = $this->crearCita(['total' => 60]);
        $vale = Vale::factory()->create(['tenant_id' => $this->tenant->id, 'valor_inicial' => 25, 'saldo' => 25]);

        $pago = $cita->cobrar('tarjeta', null, $vale);

        $this->assertSame(2, Pago::count());

        $pagoVale = Pago::where('metodo', 'vale')->sole();
        $this->assertSame('25.00', (string) $pagoVale->monto);

        // El pago devuelto es el del resto
        $this->assertSame('tarjeta', $pago->metodo);
        $this->assertSame('35.00', (string) $pago->monto);

        // El vale queda agotado
        $vale->refresh();
        $this->assertSame('0.00', (string) $vale->saldo);
        $this->assertFalse($vale->esCanjeable());
    }

    public function test_vale_no_canjeable_aborta_el_cobro_completo(): void
    {
        $cita = $this->crearCita(['total' => 30]);
        $vale = Vale::factory()->anulado()->create(['tenant_id' => $this->tenant->id]);

        try {
            $cita->cobrar('efectivo', null, $vale);
            $this->fail('Se esperaba DomainException.');
        } catch (\DomainException) {
            // esperado
        }

        // Transacción revertida: ni pagos ni cambio de estado
        $this->assertSame(0, Pago::count());
        $this->assertSame('confirmada', $cita->fresh()->estado);
    }

    public function test_vale_de_otro_tenant_es_rechazado(): void
    {
        $cita = $this->crearCita(['total' => 30]);

        $otroTenant = Tenant::factory()->create();
        $valeAjeno = Vale::factory()->create([
            'tenant_id' => $otroTenant->id,
            'saldo' => 100,
            'valor_inicial' => 100,
        ]);

        try {
            $cita->cobrar('efectivo', null, $valeAjeno);
            $this->fail('Se esperaba DomainException.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(0, Pago::count());

        // fresh() aplicaría el global scope del tenant actual y daría null
        $valeIntacto = Vale::withoutGlobalScope('tenant')->find($valeAjeno->id);
        $this->assertSame('100.00', (string) $valeIntacto->saldo);
    }

    public function test_descontar_mas_que_el_saldo_es_rechazado(): void
    {
        $vale = Vale::factory()->create(['tenant_id' => $this->tenant->id, 'valor_inicial' => 20, 'saldo' => 20]);

        $this->expectException(\DomainException::class);

        $vale->descontar(25.0);
    }

    // === Aislamiento multi-tenant ======================================

    public function test_global_scope_oculta_vales_de_otros_tenants(): void
    {
        Vale::factory()->create(['tenant_id' => $this->tenant->id]);

        $otroTenant = Tenant::factory()->create();
        Vale::factory()->create(['tenant_id' => $otroTenant->id]);

        $this->assertSame(1, Vale::count());
    }
}
