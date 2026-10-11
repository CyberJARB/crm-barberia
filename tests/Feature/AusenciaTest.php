<?php

namespace Tests\Feature;

use App\Models\Ausencia;
use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Abwesenheiten: el empleado ausente desaparece de la disponibilidad
 * pública (API v1) y de la asignación automática; rangos inclusive.
 */
class AusenciaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $apiKey;

    private Personal $barbero;

    private Servicio $servicio;

    protected function setUp(): void
    {
        parent::setUp();

        // API externa = feature del plan Premium
        $this->tenant = Tenant::factory()->create(['activo' => true, 'plan' => 'premium']);
        app()->instance('current_tenant_id', $this->tenant->id);

        $this->apiKey = 'pk_test_'.Str::random(32);
        $this->tenant->forceFill(['api_key_hash' => hash('sha256', $this->apiKey)])->save();

        $this->servicio = Servicio::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Haarschnitt',
            'duracion_minutos' => 30,
            'precio' => 25,
            'activo' => true,
        ]);

        $this->barbero = Personal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'activo' => true,
        ]);

        // Freitag (dia_semana 4, convención 0=Montag) 11–19
        HorarioPersonal::create([
            'personal_id' => $this->barbero->id,
            'dia_semana' => 4,
            'hora_inicio' => '11:00',
            'hora_fin' => '19:00',
            'activo' => true,
        ]);
    }

    private function proximoViernes(): Carbon
    {
        return Carbon::today()->next(Carbon::FRIDAY);
    }

    private function disponibilidad(string $fecha)
    {
        return $this->getJson(
            "https://localhost/api/v1/disponibilidad?fecha={$fecha}&servicio_id={$this->servicio->id}",
            ['X-API-Key' => $this->apiKey],
        );
    }

    // === Lógica del modelo =============================================

    public function test_esta_ausente_cubre_el_rango_inclusive(): void
    {
        Ausencia::create([
            'tenant_id' => $this->tenant->id,
            'personal_id' => $this->barbero->id,
            'fecha_inicio' => '2026-07-06',
            'fecha_fin' => '2026-07-10',
            'motivo' => 'vacaciones',
        ]);

        $tid = $this->tenant->id;
        $pid = $this->barbero->id;

        $this->assertFalse(Ausencia::estaAusente($tid, $pid, '2026-07-05'));
        $this->assertTrue(Ausencia::estaAusente($tid, $pid, '2026-07-06'));  // primer día
        $this->assertTrue(Ausencia::estaAusente($tid, $pid, '2026-07-08'));  // en medio
        $this->assertTrue(Ausencia::estaAusente($tid, $pid, '2026-07-10'));  // último día
        $this->assertFalse(Ausencia::estaAusente($tid, $pid, '2026-07-11'));
    }

    public function test_scope_disponibles_el_excluye_ausentes(): void
    {
        $fecha = $this->proximoViernes()->toDateString();

        Ausencia::create([
            'tenant_id' => $this->tenant->id,
            'personal_id' => $this->barbero->id,
            'fecha_inicio' => $fecha,
            'fecha_fin' => $fecha,
            'motivo' => 'enfermedad',
        ]);

        $this->assertSame(0, Personal::disponiblesEl($fecha)->count());
        $this->assertSame(1, Personal::disponiblesEl(
            $this->proximoViernes()->addWeek()->toDateString()
        )->count());
    }

    // === API pública ===================================================

    public function test_api_no_ofrece_slots_si_el_unico_barbero_esta_de_vacaciones(): void
    {
        $viernes = $this->proximoViernes();

        // Sin ausencia: hay slots
        $this->disponibilidad($viernes->toDateString())
            ->assertOk()
            ->assertJsonPath('abierto', true);

        Ausencia::create([
            'tenant_id' => $this->tenant->id,
            'personal_id' => $this->barbero->id,
            'fecha_inicio' => $viernes->toDateString(),
            'fecha_fin' => $viernes->toDateString(),
            'motivo' => 'vacaciones',
        ]);

        // Con ausencia: cerrado (era el único empleado del día)
        $this->disponibilidad($viernes->toDateString())
            ->assertOk()
            ->assertJsonPath('abierto', false);
    }

    public function test_api_mantiene_slots_si_otro_barbero_sigue_trabajando(): void
    {
        $viernes = $this->proximoViernes();

        $segundo = Personal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'activo' => true,
        ]);
        HorarioPersonal::create([
            'personal_id' => $segundo->id,
            'dia_semana' => 4,
            'hora_inicio' => '11:00',
            'hora_fin' => '19:00',
            'activo' => true,
        ]);

        Ausencia::create([
            'tenant_id' => $this->tenant->id,
            'personal_id' => $this->barbero->id,
            'fecha_inicio' => $viernes->toDateString(),
            'fecha_fin' => $viernes->toDateString(),
            'motivo' => 'vacaciones',
        ]);

        $this->disponibilidad($viernes->toDateString())
            ->assertOk()
            ->assertJsonPath('abierto', true);
    }
}
