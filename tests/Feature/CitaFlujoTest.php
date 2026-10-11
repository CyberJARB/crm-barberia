<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CWE-841 — Máquina de estados de la cita (workflow enforcement).
 * CWE-1284 — Anti doble reserva (solape de horarios por empleado).
 * Cobro transaccional: Cita::cobrar() crea el Pago con monto server-side.
 */
class CitaFlujoTest extends TestCase
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
        ], $atributos));
    }

    // === Máquina de estados ============================================

    public function test_transicion_valida_cambia_estado_y_audita(): void
    {
        $cita = $this->crearCita(['estado' => 'pendiente']);

        $cita->transicionarA('confirmada');

        $this->assertSame('confirmada', $cita->fresh()->estado);
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'CITA_ESTADO',
            'entidad' => 'Cita',
            'entidad_id' => $cita->id,
        ]);
    }

    public function test_transicion_invalida_es_rechazada(): void
    {
        $cita = $this->crearCita(['estado' => 'completada']);

        $this->expectException(\DomainException::class);

        $cita->transicionarA('confirmada');
    }

    public function test_cita_cancelada_no_puede_completarse(): void
    {
        $cita = $this->crearCita(['estado' => 'cancelada']);

        $this->assertFalse($cita->puedeTransicionarA('completada'));
    }

    // === Anti doble reserva ============================================

    public function test_detecta_solape_de_horario_del_mismo_empleado(): void
    {
        $personal = Personal::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->crearCita([
            'personal_id' => $personal->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'confirmada',
        ]);

        // Se solapa parcialmente
        $this->assertTrue(Cita::haySolape(
            $this->tenant->id, $personal->id, '2026-07-01', '10:30:00', '11:30:00'
        ));

        // Intervalos contiguos NO se solapan (semiabiertos)
        $this->assertFalse(Cita::haySolape(
            $this->tenant->id, $personal->id, '2026-07-01', '11:00:00', '12:00:00'
        ));

        // Otro día no se solapa
        $this->assertFalse(Cita::haySolape(
            $this->tenant->id, $personal->id, '2026-07-02', '10:30:00', '11:30:00'
        ));
    }

    public function test_solape_ignora_otro_empleado_y_citas_canceladas(): void
    {
        $personalA = Personal::factory()->create(['tenant_id' => $this->tenant->id]);
        $personalB = Personal::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->crearCita([
            'personal_id' => $personalA->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'cancelada',
        ]);

        // La cita cancelada no bloquea la agenda
        $this->assertFalse(Cita::haySolape(
            $this->tenant->id, $personalA->id, '2026-07-01', '10:00:00', '11:00:00'
        ));

        // Otro empleado a la misma hora no es solape
        $this->crearCita([
            'personal_id' => $personalA->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '14:00:00',
            'hora_fin' => '15:00:00',
            'estado' => 'confirmada',
        ]);

        $this->assertFalse(Cita::haySolape(
            $this->tenant->id, $personalB->id, '2026-07-01', '14:00:00', '15:00:00'
        ));
    }

    public function test_al_editar_una_cita_se_ignora_a_si_misma(): void
    {
        $personal = Personal::factory()->create(['tenant_id' => $this->tenant->id]);

        $cita = $this->crearCita([
            'personal_id' => $personal->id,
            'fecha' => '2026-07-01',
            'hora_inicio' => '10:00:00',
            'hora_fin' => '11:00:00',
            'estado' => 'confirmada',
        ]);

        $this->assertFalse(Cita::haySolape(
            $this->tenant->id, $personal->id, '2026-07-01', '10:00:00', '11:00:00', $cita->id
        ));
    }

    // === Cobro =========================================================

    public function test_cobrar_completa_la_cita_y_crea_el_pago(): void
    {
        $cita = $this->crearCita(['estado' => 'confirmada', 'total' => 45.50]);

        $pago = $cita->cobrar('efectivo', 'BON-001');

        $cita->refresh();
        $this->assertSame('completada', $cita->estado);

        $this->assertInstanceOf(Pago::class, $pago);
        $this->assertSame('45.50', (string) $pago->monto);
        $this->assertSame('efectivo', $pago->metodo);
        $this->assertSame($cita->cliente_id, $pago->cliente_id);
        $this->assertSame($this->tenant->id, $pago->tenant_id);

        // Fidelización del cliente actualizada
        $this->assertSame(1, $cita->cliente->fresh()->total_visitas);

        // Rastro de auditoría (OWASP A09)
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'CITA_COBRADA',
            'entidad' => 'Pago',
            'entidad_id' => $pago->id,
        ]);
    }

    public function test_cobrar_con_metodo_invalido_es_rechazado(): void
    {
        $cita = $this->crearCita(['estado' => 'confirmada', 'total' => 30]);

        $this->expectException(\InvalidArgumentException::class);

        $cita->cobrar('paypal');
    }

    public function test_cobrar_una_cita_cancelada_no_crea_pago(): void
    {
        $cita = $this->crearCita(['estado' => 'cancelada', 'total' => 30]);

        try {
            $cita->cobrar('efectivo');
            $this->fail('Se esperaba DomainException.');
        } catch (\DomainException) {
            // esperado
        }

        $this->assertSame(0, Pago::count());
        $this->assertSame('cancelada', $cita->fresh()->estado);
    }
}
