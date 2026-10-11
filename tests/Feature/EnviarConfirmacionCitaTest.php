<?php

namespace Tests\Feature;

use App\Jobs\EnviarConfirmacionCita;
use App\Mail\ConfirmacionCitaMail;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarConfirmacionCitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_confirmacion_cuando_cita_esta_confirmada(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_confirmacion_activo' => true]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create(['estado' => 'confirmada']);

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarConfirmacionCita($cita))->handle();

        Mail::assertSent(ConfirmacionCitaMail::class, function ($mail) use ($cliente) {
            return $mail->hasTo($cliente->email);
        });
    }

    public function test_no_envia_si_cita_cancelada(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_confirmacion_activo' => true]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->cancelada()->create();

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarConfirmacionCita($cita))->handle();

        Mail::assertNotSent(ConfirmacionCitaMail::class);
    }

    public function test_no_envia_si_confirmacion_desactivada(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_confirmacion_activo' => false]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create(['estado' => 'confirmada']);

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarConfirmacionCita($cita))->handle();

        Mail::assertNotSent(ConfirmacionCitaMail::class);
    }
}
