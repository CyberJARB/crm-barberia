<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * API pública de reservas v1 (sitios web de los tenants).
 *
 * Cubre: autenticación por API key (OWASP A07), aislamiento entre
 * tenants (OWASP A01), cálculo de disponibilidad por capacidad,
 * convención dia_semana 0=Montag, y la revalidación server-side del
 * slot al crear la cita (anti doble-reserva / CWE-362).
 *
 * Nota: ForceHttps redirige en testing → todas las URLs van con https://.
 */
class ReservaPublicaApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private string $apiKey;

    private Servicio $servicio;

    private Personal $barbero;

    protected function setUp(): void
    {
        parent::setUp();

        // API externa = feature del plan Premium
        $this->tenant = Tenant::factory()->create(['activo' => true, 'plan' => 'premium']);

        $this->apiKey = 'pk_test_'.Str::random(32);
        $this->tenant->forceFill(['api_key_hash' => hash('sha256', $this->apiKey)])->save();

        $this->servicio = Servicio::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Haarschnitt & Fade',
            'duracion_minutos' => 45,
            'precio' => 25,
            'activo' => true,
        ]);

        $this->barbero = Personal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'activo' => true,
        ]);

        // Freitag (dia_semana 4, convención del panel: 0=Montag) 11–19
        HorarioPersonal::create([
            'personal_id' => $this->barbero->id,
            'dia_semana' => 4,
            'hora_inicio' => '11:00',
            'hora_fin' => '19:00',
            'activo' => true,
        ]);
    }

    /** Próximo viernes (siempre en el futuro, sin chocar con la antelación de hoy). */
    private function proximoViernes(): Carbon
    {
        return Carbon::today()->next(Carbon::FRIDAY);
    }

    private function getApi(string $path, ?string $key = null)
    {
        return $this->getJson('https://localhost/api/v1'.$path, [
            'X-API-Key' => $key ?? $this->apiKey,
        ]);
    }

    private function postApi(string $path, array $datos, ?string $key = null)
    {
        return $this->postJson('https://localhost/api/v1'.$path, $datos, [
            'X-API-Key' => $key ?? $this->apiKey,
        ]);
    }

    private function payloadCita(array $extra = []): array
    {
        return array_merge([
            'nombre' => 'Moussa',
            'apellido' => 'Keita',
            'telefono' => '+49 170 1234567',
            'servicio_id' => $this->servicio->id,
            'fecha' => $this->proximoViernes()->toDateString(),
            'hora' => '11:00',
            'lang' => 'fr',
        ], $extra);
    }

    // === Autenticación (OWASP A07) ===

    public function test_sin_api_key_devuelve_401(): void
    {
        $this->getJson('https://localhost/api/v1/servicios')->assertStatus(401);
    }

    public function test_api_key_invalida_devuelve_401(): void
    {
        $this->getApi('/servicios', 'pk_clave_falsa')->assertStatus(401);
    }

    public function test_api_key_de_tenant_inactivo_devuelve_401(): void
    {
        $this->tenant->update(['activo' => false]);

        $this->getApi('/servicios')->assertStatus(401);
    }

    // === Servicios + aislamiento (OWASP A01) ===

    public function test_servicios_devuelve_solo_los_activos_del_tenant(): void
    {
        Servicio::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Servicio inactivo',
            'duracion_minutos' => 30,
            'precio' => 10,
            'activo' => false,
        ]);

        $otroTenant = Tenant::factory()->create(['activo' => true]);
        Servicio::create([
            'tenant_id' => $otroTenant->id,
            'nombre' => 'Servicio ajeno',
            'duracion_minutos' => 30,
            'precio' => 10,
            'activo' => true,
        ]);

        $respuesta = $this->getApi('/servicios')->assertOk()->json('servicios');

        $nombres = array_column($respuesta, 'nombre');
        $this->assertSame(['Haarschnitt & Fade'], $nombres);
    }

    public function test_disponibilidad_de_servicio_ajeno_devuelve_404(): void
    {
        $otroTenant = Tenant::factory()->create(['activo' => true]);
        $servicioAjeno = Servicio::create([
            'tenant_id' => $otroTenant->id,
            'nombre' => 'Ajeno',
            'duracion_minutos' => 30,
            'precio' => 10,
            'activo' => true,
        ]);

        $fecha = $this->proximoViernes()->toDateString();
        $this->getApi("/disponibilidad?fecha={$fecha}&servicio_id={$servicioAjeno->id}")
            ->assertStatus(404);
    }

    // === Disponibilidad ===

    public function test_viernes_devuelve_slots_segun_horario_y_duracion(): void
    {
        $fecha = $this->proximoViernes()->toDateString();

        $respuesta = $this->getApi("/disponibilidad?fecha={$fecha}&servicio_id={$this->servicio->id}")
            ->assertOk()
            ->json();

        $this->assertTrue($respuesta['abierto']);
        $horas = array_column($respuesta['slots'], 'hora');

        // 11:00 primero; último 18:00 (18:00 + 45min = 18:45 ≤ 19:00; 18:30 ya no cabe)
        $this->assertSame('11:00', $horas[0]);
        $this->assertSame('18:00', end($horas));
        $this->assertContains('14:30', $horas);
    }

    public function test_dia_sin_horario_aparece_cerrado(): void
    {
        // Domingo: no hay fila de horario → cerrado
        $domingo = Carbon::today()->next(Carbon::SUNDAY)->toDateString();

        $this->getApi("/disponibilidad?fecha={$domingo}&servicio_id={$this->servicio->id}")
            ->assertOk()
            ->assertJson(['abierto' => false, 'slots' => []]);
    }

    public function test_fecha_pasada_devuelve_422(): void
    {
        $ayer = Carbon::yesterday()->toDateString();

        $this->getApi("/disponibilidad?fecha={$ayer}&servicio_id={$this->servicio->id}")
            ->assertStatus(422);
    }

    public function test_fecha_demasiado_lejana_devuelve_422(): void
    {
        $lejana = Carbon::today()->addDays(90)->toDateString();

        $this->getApi("/disponibilidad?fecha={$lejana}&servicio_id={$this->servicio->id}")
            ->assertStatus(422);
    }

    public function test_cita_existente_bloquea_slots_que_solapa(): void
    {
        $fecha = $this->proximoViernes();
        $this->crearCitaOcupando($fecha, '11:00', '11:45');

        $slots = $this->getApi("/disponibilidad?fecha={$fecha->toDateString()}&servicio_id={$this->servicio->id}")
            ->assertOk()
            ->json('slots');

        $porHora = array_column($slots, 'disponible', 'hora');

        // Con 1 barbero: 11:00 y 11:30 solapan la cita (11:00–11:45); 12:00 libre
        $this->assertFalse($porHora['11:00']);
        $this->assertFalse($porHora['11:30']);
        $this->assertTrue($porHora['12:00']);
    }

    public function test_capacidad_con_dos_barberos_un_slot_admite_dos_citas(): void
    {
        // Segundo barbero con el mismo horario de viernes
        $barbero2 = Personal::factory()->create([
            'tenant_id' => $this->tenant->id,
            'activo' => true,
        ]);
        HorarioPersonal::create([
            'personal_id' => $barbero2->id,
            'dia_semana' => 4,
            'hora_inicio' => '11:00',
            'hora_fin' => '19:00',
            'activo' => true,
        ]);

        $fecha = $this->proximoViernes();
        $this->crearCitaOcupando($fecha, '11:00', '11:45');

        $slots = $this->getApi("/disponibilidad?fecha={$fecha->toDateString()}&servicio_id={$this->servicio->id}")
            ->json('slots');
        $porHora = array_column($slots, 'disponible', 'hora');
        $this->assertTrue($porHora['11:00'], 'Con 2 barberos y 1 cita, el slot sigue libre');

        $this->crearCitaOcupando($fecha, '11:00', '11:45');

        $slots = $this->getApi("/disponibilidad?fecha={$fecha->toDateString()}&servicio_id={$this->servicio->id}")
            ->json('slots');
        $porHora = array_column($slots, 'disponible', 'hora');
        $this->assertFalse($porHora['11:00'], 'Con 2 citas se agota la capacidad');
    }

    // === Crear cita ===

    public function test_crear_cita_persiste_pendiente_con_cliente_y_servicio(): void
    {
        $respuesta = $this->postApi('/citas', $this->payloadCita(['notas' => 'Fade corto']))
            ->assertStatus(201)
            ->json();

        $this->assertSame('pendiente', $respuesta['estado']);

        $cita = Cita::withoutGlobalScope('tenant')->findOrFail($respuesta['id']);
        $this->assertSame($this->tenant->id, $cita->tenant_id);
        $this->assertSame('11:45', substr($cita->hora_fin, 0, 5));
        $this->assertSame('25.00', (string) $cita->total);
        $this->assertStringContainsString('[Web · fr]', $cita->notas);
        $this->assertStringContainsString('Fade corto', $cita->notas);
        $this->assertSame($this->barbero->id, $cita->personal_id);

        $cliente = Cliente::withoutGlobalScope('tenant')->find($cita->cliente_id);
        $this->assertSame('Moussa Keita', $cliente->nombre);
        $this->assertSame('+49 170 1234567', $cliente->telefono); // descifrado por accessor

        $this->assertTrue($cita->servicios->contains($this->servicio->id));
    }

    public function test_crear_cita_reutiliza_cliente_existente_por_nombre(): void
    {
        $this->postApi('/citas', $this->payloadCita())->assertStatus(201);
        $this->postApi('/citas', $this->payloadCita(['hora' => '15:00']))->assertStatus(201);

        $total = Cliente::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->count();

        $this->assertSame(1, $total);
    }

    public function test_slot_ocupado_devuelve_422_con_codigo(): void
    {
        $fecha = $this->proximoViernes();
        $this->crearCitaOcupando($fecha, '11:00', '11:45');

        $this->postApi('/citas', $this->payloadCita())
            ->assertStatus(422)
            ->assertJson(['code' => 'SLOT_OCUPADO']);
    }

    public function test_hora_fuera_del_horario_devuelve_422(): void
    {
        $this->postApi('/citas', $this->payloadCita(['hora' => '09:00']))
            ->assertStatus(422)
            ->assertJson(['code' => 'SLOT_OCUPADO']);
    }

    public function test_validacion_de_campos_obligatorios(): void
    {
        $this->postApi('/citas', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['nombre', 'apellido', 'telefono', 'servicio_id', 'fecha', 'hora']);
    }

    public function test_cita_cancelada_no_bloquea_el_slot(): void
    {
        $fecha = $this->proximoViernes();
        $this->crearCitaOcupando($fecha, '11:00', '11:45', 'cancelada');

        $slots = $this->getApi("/disponibilidad?fecha={$fecha->toDateString()}&servicio_id={$this->servicio->id}")
            ->json('slots');
        $porHora = array_column($slots, 'disponible', 'hora');

        $this->assertTrue($porHora['11:00']);
    }

    // ------------------------------------------------------------------

    private function crearCitaOcupando(Carbon $fecha, string $inicio, string $fin, string $estado = 'confirmada'): Cita
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        return Cita::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'personal_id' => $this->barbero->id,
            'fecha' => $fecha->toDateString(),
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
            'total' => 25,
        ]);
    }
}
