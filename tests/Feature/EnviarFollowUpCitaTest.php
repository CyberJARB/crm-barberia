<?php

namespace Tests\Feature;

use App\Jobs\EnviarFollowUpCita;
use App\Mail\FollowUpCitaMail;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EnviarFollowUpCitaTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_followup_despues_de_la_cita(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create([
            'email_followup_activo' => true,
            'email_followup_horas' => 2,
        ]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create(['estado' => 'completada']);

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarFollowUpCita($cita))->handle();

        Mail::assertSent(FollowUpCitaMail::class, fn ($m) => $m->hasTo($cliente->email));
    }

    public function test_no_envia_si_followup_desactivado(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_followup_activo' => false]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->create();

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarFollowUpCita($cita))->handle();

        Mail::assertNotSent(FollowUpCitaMail::class);
    }

    public function test_no_envia_si_cita_cancelada(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['email_followup_activo' => true]);
        $cliente = Cliente::factory()->for($tenant)->create(['email' => 'kunde@example.de']);
        $cita = Cita::factory()->for($tenant)->for($cliente)->cancelada()->create();

        app()->instance('current_tenant_id', $tenant->id);

        (new EnviarFollowUpCita($cita))->handle();

        Mail::assertNotSent(FollowUpCitaMail::class);
    }
}
