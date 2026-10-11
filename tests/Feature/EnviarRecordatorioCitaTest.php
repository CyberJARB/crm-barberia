<?php

namespace Tests\Feature;

use App\Jobs\EnviarRecordatorioCita;
use App\Mail\RecordatorioCitaMail;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarRecordatorioCitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_recordatorio_y_marca_enviado(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_recordatorio_activo' => true]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create([
            'estado' => 'confirmada',
            'recordatorio_enviado' => false,
        ]);

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarRecordatorioCita($cita))->handle();

        Mail::assertSent(RecordatorioCitaMail::class, fn ($m) => $m->hasTo($cliente->email));
        $this->assertTrue($cita->fresh()->recordatorio_enviado);
    }

    public function test_no_envia_si_recordatorio_ya_enviado(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_recordatorio_activo' => true]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->recordatorioEnviado()->create();

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarRecordatorioCita($cita))->handle();

        Mail::assertNotSent(RecordatorioCitaMail::class);
    }

    public function test_no_envia_si_recordatorio_desactivado(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_recordatorio_activo' => false]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create(['estado' => 'confirmada']);

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarRecordatorioCita($cita))->handle();

        Mail::assertNotSent(RecordatorioCitaMail::class);
    }
}
