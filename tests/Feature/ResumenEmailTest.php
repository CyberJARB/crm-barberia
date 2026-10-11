<?php

namespace Tests\Feature;

use App\Jobs\EnviarResumenTenant;
use App\Mail\ResumenMail;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Resumen del negocio: selección por frecuencia, destinatarios correctos
 * (solo gestión activa) y KPIs acotados por tenant (OWASP A01).
 */
class ResumenEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_las_7_solo_le_toca_a_la_cadencia_manana(): void
    {
        Queue::fake();

        $manana = Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => 'manana']);
        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => '3h']);
        Tenant::factory()->create(['resumen_activo' => false, 'resumen_frecuencia' => 'manana']);
        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => 'manana', 'activo' => false]);

        $this->artisan('emails:resumen', ['--hora' => 7])
            ->expectsOutputToContain('1 resumen(es) despachados')
            ->assertExitCode(0);

        Queue::assertPushed(
            EnviarResumenTenant::class,
            fn (EnviarResumenTenant $job): bool => $job->tenant->id === $manana->id
        );
        Queue::assertPushed(EnviarResumenTenant::class, 1);
    }

    public function test_a_las_8_coinciden_las_cadencias_3h_6h_y_12h(): void
    {
        Queue::fake();

        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => '3h']);
        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => '6h']);
        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => '12h']);
        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => 'manana']);

        $this->artisan('emails:resumen', ['--hora' => 8])
            ->expectsOutputToContain('3 resumen(es) despachados')
            ->assertExitCode(0);

        Queue::assertPushed(EnviarResumenTenant::class, 3);
    }

    public function test_a_una_hora_sin_envios_no_despacha_nada(): void
    {
        Queue::fake();

        Tenant::factory()->create(['resumen_activo' => true, 'resumen_frecuencia' => '3h']);

        $this->artisan('emails:resumen', ['--hora' => 9])
            ->expectsOutputToContain('no toca ningún envío')
            ->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_hora_invalida_es_rechazada(): void
    {
        Queue::fake();

        $this->artisan('emails:resumen', ['--hora' => 25])
            ->assertExitCode(1);

        Queue::assertNothingPushed();
    }

    public function test_el_job_envia_a_propietarios_y_admins_pero_no_a_recepcion(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['resumen_activo' => true]);

        $propietario = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'admin']);
        $recepcion = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'recepcionista']);
        $adminInactivo = User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'admin', 'activo' => false]);

        (new EnviarResumenTenant($tenant))->handle();

        Mail::assertSent(ResumenMail::class, fn (ResumenMail $m): bool => $m->hasTo($propietario->email));
        Mail::assertSent(ResumenMail::class, fn (ResumenMail $m): bool => $m->hasTo($admin->email));
        Mail::assertNotSent(ResumenMail::class, fn (ResumenMail $m): bool => $m->hasTo($recepcion->email));
        Mail::assertNotSent(ResumenMail::class, fn (ResumenMail $m): bool => $m->hasTo($adminInactivo->email));
    }

    public function test_el_job_no_envia_si_el_tenant_lo_desactivo(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['resumen_activo' => false]);
        User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        (new EnviarResumenTenant($tenant))->handle();

        Mail::assertNothingSent();
    }

    public function test_los_kpis_solo_cuentan_datos_del_propio_tenant(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create(['resumen_activo' => true]);
        User::factory()->create(['tenant_id' => $tenant->id, 'rol' => 'propietario']);

        // Datos del tenant: 1 cita hoy + 1 pago hoy
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);
        Cita::factory()->create([
            'tenant_id' => $tenant->id,
            'cliente_id' => $cliente->id,
            'fecha' => today(),
            'hora_inicio' => '23:30:00',
            'hora_fin' => '23:59:00',
            'estado' => 'confirmada',
        ]);
        Pago::factory()->create(['tenant_id' => $tenant->id, 'monto' => 50]);

        // Ruido de otro tenant
        $otro = Tenant::factory()->create();
        Pago::factory()->create(['tenant_id' => $otro->id, 'monto' => 999]);

        (new EnviarResumenTenant($tenant))->handle();

        Mail::assertSent(ResumenMail::class, function (ResumenMail $mail): bool {
            return $mail->stats['citasHoy'] === 1
                && $mail->stats['ingresosHoy'] === 50.0
                && $mail->stats['nuevosClientes24h'] === 1;
        });
    }
}
