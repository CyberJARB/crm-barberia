<?php

namespace Tests\Feature;

use App\Jobs\EnviarRecordatorioMensaje;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Tenant;
use App\Services\Mensajeria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Recordatorios WhatsApp/SMS — cumplimiento UE/DE:
 * §7 UWG (opt-in con prueba y revocación), DSGVO Art. 5 (minimización),
 * Art. 7 (consentimiento auditado) y revalidación en el envío.
 */
class MensajeriaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // WhatsApp = feature desde el plan Professionell
        $this->tenant = Tenant::factory()->create(['mensajeria_activa' => true, 'plan' => 'profesional']);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function clienteConOptin(array $extra = []): Cliente
    {
        return Cliente::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'telefono' => '0170 5556677',
            'mensajeria_optin' => true,
        ], $extra));
    }

    private function citaManana(Cliente $cliente): Cita
    {
        // A 2 días vista: el recordatorio (24 h antes) siempre queda en el
        // futuro, sea cual sea la hora a la que corra la suite
        return Cita::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $cliente->id,
            'fecha' => now()->addDays(2)->toDateString(),
            'hora_inicio' => '15:00:00',
            'hora_fin' => '15:30:00',
            'estado' => 'confirmada',
        ]);
    }

    // === Consentimiento (§7 UWG / DSGVO Art. 7) ========================

    public function test_el_optin_se_timestampea_y_audita(): void
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertNull($cliente->mensajeria_optin_en);

        $cliente->update(['mensajeria_optin' => true]);

        $this->assertNotNull($cliente->fresh()->mensajeria_optin_en);
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'CONSENTIMIENTO_MENSAJERIA',
            'entidad' => 'Cliente',
            'entidad_id' => $cliente->id,
        ]);
    }

    public function test_la_revocacion_borra_el_timestamp_y_se_audita(): void
    {
        $cliente = $this->clienteConOptin();

        $cliente->update(['mensajeria_optin' => false]);

        $this->assertNull($cliente->fresh()->mensajeria_optin_en);
        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'REVOCACION_MENSAJERIA',
            'entidad' => 'Cliente',
            'entidad_id' => $cliente->id,
        ]);
    }

    public function test_anonimizar_revoca_el_consentimiento(): void
    {
        $cliente = $this->clienteConOptin();

        $cliente->anonimizar();

        $this->assertFalse($cliente->fresh()->mensajeria_optin);
    }

    // === Programación al crear cita ====================================

    public function test_crear_cita_programa_mensaje_solo_con_optin(): void
    {
        Queue::fake();

        $conOptin = $this->clienteConOptin();
        $this->citaManana($conOptin);

        $sinOptin = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->citaManana($sinOptin);

        Queue::assertPushed(EnviarRecordatorioMensaje::class, 1);
    }

    public function test_sin_flag_del_tenant_no_se_programa_nada(): void
    {
        Queue::fake();

        $this->tenant->update(['mensajeria_activa' => false]);

        $this->citaManana($this->clienteConOptin());

        Queue::assertNotPushed(EnviarRecordatorioMensaje::class);
    }

    // === Envío (driver log) ===========================================

    public function test_el_job_envia_con_texto_minimizado_y_audita(): void
    {
        // La cola sync de tests ejecutaría el job ya al crear la cita
        Queue::fake();

        Log::shouldReceive('info')->once()->withArgs(function (string $mensaje): bool {
            // Minimización: negocio+fecha+hora+opt-out, sin servicio ni notas
            return str_contains($mensaje, 'whatsapp → +491705556677')
                && str_contains($mensaje, '15:00 Uhr')
                && str_contains($mensaje, 'STOP');
        });

        $cita = $this->citaManana($this->clienteConOptin());

        (new EnviarRecordatorioMensaje($cita))->handle(new Mensajeria);

        $this->assertDatabaseHas('audit_logs', [
            'accion' => 'MENSAJE_ENVIADO',
            'entidad' => 'Cita',
            'entidad_id' => $cita->id,
        ]);
    }

    public function test_el_job_no_envia_si_el_cliente_revoco_despues_de_agendar(): void
    {
        Queue::fake();
        Log::shouldReceive('info')->never();

        $cliente = $this->clienteConOptin();
        $cita = $this->citaManana($cliente);

        // Revoca tras agendar — el job debe respetarlo (§7 UWG)
        $cliente->update(['mensajeria_optin' => false]);

        (new EnviarRecordatorioMensaje($cita))->handle(new Mensajeria);

        $this->assertDatabaseMissing('audit_logs', ['accion' => 'MENSAJE_ENVIADO']);
    }

    public function test_el_job_no_envia_si_la_cita_fue_cancelada(): void
    {
        Queue::fake();
        Log::shouldReceive('info')->never();

        $cita = $this->citaManana($this->clienteConOptin());
        $cita->update(['estado' => 'cancelada']);

        (new EnviarRecordatorioMensaje($cita))->handle(new Mensajeria);

        $this->assertDatabaseMissing('audit_logs', ['accion' => 'MENSAJE_ENVIADO']);
    }

    // === Driver Twilio =================================================

    public function test_twilio_recibe_la_peticion_correcta(): void
    {
        config([
            'mensajeria.driver' => 'twilio',
            'mensajeria.twilio.sid' => 'AC_test',
            'mensajeria.twilio.token' => 'secreto',
            'mensajeria.twilio.desde_whatsapp' => 'whatsapp:+4915700000000',
        ]);

        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM123'], 201)]);

        (new Mensajeria)->enviar('whatsapp', '0170 5556677', 'Test');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/Accounts/AC_test/Messages.json')
            && $request['To'] === 'whatsapp:+491705556677'
            && $request['From'] === 'whatsapp:+4915700000000'
        );
    }

    public function test_error_de_twilio_lanza_excepcion_para_reintento(): void
    {
        config([
            'mensajeria.driver' => 'twilio',
            'mensajeria.twilio.sid' => 'AC_test',
            'mensajeria.twilio.token' => 'secreto',
            'mensajeria.twilio.desde_sms' => '+4915700000000',
        ]);

        Http::fake(['api.twilio.com/*' => Http::response(['error' => 'invalid'], 400)]);

        $this->expectException(\RuntimeException::class);

        (new Mensajeria)->enviar('sms', '0170 5556677', 'Test');
    }

    // === Normalización de teléfonos ====================================

    public function test_normalizacion_de_telefonos_alemanes_e_internacionales(): void
    {
        $m = new Mensajeria;

        $this->assertSame('+491705556677', $m->normalizarTelefono('0170 555 66 77'));
        $this->assertSame('+491705556677', $m->normalizarTelefono('+49 170 5556677'));
        $this->assertSame('+491705556677', $m->normalizarTelefono('0049 170 5556677'));
        $this->assertSame('+34612345678', $m->normalizarTelefono('+34 612 345 678'));
        $this->assertNull($m->normalizarTelefono('123'));
        $this->assertNull($m->normalizarTelefono('no-es-telefono'));
    }
}
