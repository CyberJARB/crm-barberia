<?php

namespace Tests\Feature;

use App\Jobs\EnviarFelicitacionGeburtstag;
use App\Mail\GeburtstagMail;
use App\Models\Cliente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Felicitaciones de cumpleaños: selección correcta de destinatarios
 * (solo cumpleaños de hoy, tenants opt-in, clientes activos con email)
 * y envío respetuoso con GDPR (el job revalida todo antes de enviar).
 */
class GeburtstagEmailTest extends TestCase
{
    use RefreshDatabase;

    private function tenantConGeburtstag(bool $activo = true): Tenant
    {
        return Tenant::factory()->create(['email_geburtstag_activo' => $activo]);
    }

    private function clienteCumpleHoy(Tenant $tenant, array $extra = []): Cliente
    {
        return Cliente::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'fecha_nacimiento' => now()->subYears(30)->toDateString(),
            'email' => 'kunde@example.de',
            'activo' => true,
        ], $extra));
    }

    public function test_despacha_jobs_solo_para_cumpleanos_de_hoy_con_optin(): void
    {
        Queue::fake();

        $tenantOptIn = $this->tenantConGeburtstag();
        $tenantOptOut = $this->tenantConGeburtstag(false);

        $cumpleHoy = $this->clienteCumpleHoy($tenantOptIn);

        // No deben recibir: otro día, tenant sin opt-in, sin email, inactivo
        $this->clienteCumpleHoy($tenantOptIn, [
            'fecha_nacimiento' => now()->subYears(25)->addDays(10)->toDateString(),
        ]);
        $this->clienteCumpleHoy($tenantOptOut);
        $this->clienteCumpleHoy($tenantOptIn, ['email' => null]);
        $this->clienteCumpleHoy($tenantOptIn, ['activo' => false]);

        $this->artisan('emails:geburtstage')
            ->expectsOutputToContain('1 felicitación(es) despachadas')
            ->assertExitCode(0);

        Queue::assertPushed(EnviarFelicitacionGeburtstag::class, 1);
        Queue::assertPushed(
            EnviarFelicitacionGeburtstag::class,
            fn (EnviarFelicitacionGeburtstag $job): bool => $job->cliente->id === $cumpleHoy->id
        );
    }

    public function test_dry_run_no_despacha_nada(): void
    {
        Queue::fake();

        $this->clienteCumpleHoy($this->tenantConGeburtstag());

        $this->artisan('emails:geburtstage', ['--dry-run' => true])
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_el_job_envia_el_email_de_cumpleanos(): void
    {
        Mail::fake();

        $cliente = $this->clienteCumpleHoy($this->tenantConGeburtstag());

        (new EnviarFelicitacionGeburtstag($cliente))->handle();

        Mail::assertSent(
            GeburtstagMail::class,
            fn (GeburtstagMail $mail): bool => $mail->hasTo($cliente->email)
        );
    }

    public function test_el_job_no_envia_si_el_tenant_desactivo_la_opcion(): void
    {
        Mail::fake();

        $cliente = $this->clienteCumpleHoy($this->tenantConGeburtstag(false));

        (new EnviarFelicitacionGeburtstag($cliente))->handle();

        Mail::assertNothingSent();
    }

    public function test_el_job_no_envia_a_clientes_anonimizados(): void
    {
        Mail::fake();

        $cliente = $this->clienteCumpleHoy($this->tenantConGeburtstag());
        $cliente->anonimizar();

        (new EnviarFelicitacionGeburtstag($cliente))->handle();

        Mail::assertNothingSent();
    }
}
