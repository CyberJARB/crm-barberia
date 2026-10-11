<?php

namespace Tests\Feature;

use App\Livewire\ReservarPagina;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Página de reservas alojada /reservar/{slug}: flujo completo de reserva,
 * aislamiento por tenant (OWASP A01) y revalidación server-side del slot.
 */
class ReservarPaginaTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Servicio $servicio;

    private Personal $barbero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'activo' => true,
            'slug' => 'demo-salon',
            'nombre' => 'Salon Demo GmbH',
        ]);

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

    public function test_la_pagina_renderiza_con_la_marca_del_negocio(): void
    {
        $this->get('https://localhost/reservar/demo-salon')
            ->assertOk()
            ->assertSee('Salon Demo GmbH')
            ->assertSee('Haarschnitt')
            ->assertSee('Online-Termin buchen');
    }

    public function test_slug_desconocido_o_tenant_inactivo_devuelve_404(): void
    {
        $this->get('https://localhost/reservar/no-existe')->assertNotFound();

        $this->tenant->update(['activo' => false]);
        $this->get('https://localhost/reservar/demo-salon')->assertNotFound();
    }

    public function test_flujo_completo_crea_cita_pendiente_y_cliente(): void
    {
        $viernes = $this->proximoViernes();

        Livewire::test(ReservarPagina::class, ['slug' => 'demo-salon'])
            ->call('elegirServicio', $this->servicio->id)
            ->call('elegirFecha', $viernes->toDateString())
            ->call('elegirHora', '11:00')
            ->set('nombre', 'Lena')
            ->set('apellido', 'Schmidt')
            ->set('telefono', '+49 170 5556677')
            ->set('email', 'lena@example.de')
            ->call('reservar')
            ->assertHasNoErrors()
            ->assertSet('exito.servicio', 'Haarschnitt');

        $cita = Cita::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame('pendiente', $cita->estado);
        $this->assertSame($viernes->toDateString(), $cita->fecha->toDateString());
        $this->assertSame('11:00:00', (string) $cita->hora_inicio);
        $this->assertSame('25.00', (string) $cita->total);
        $this->assertStringContainsString('[Online]', (string) $cita->notas);

        $cliente = Cliente::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->sole();
        $this->assertSame('Lena Schmidt', $cliente->nombre);
        $this->assertSame('lena@example.de', $cliente->email);
    }

    public function test_no_se_puede_elegir_un_servicio_de_otro_tenant(): void
    {
        $otroTenant = Tenant::factory()->create(['activo' => true]);
        $servicioAjeno = Servicio::create([
            'tenant_id' => $otroTenant->id,
            'nombre' => 'Maniküre',
            'duracion_minutos' => 45,
            'precio' => 35,
            'activo' => true,
        ]);

        // El global scope del tenant hace que el findOrFail no lo encuentre
        $this->expectException(ModelNotFoundException::class);

        Livewire::test(ReservarPagina::class, ['slug' => 'demo-salon'])
            ->call('elegirServicio', $servicioAjeno->id);
    }

    public function test_slot_ocupado_es_rechazado_con_mensaje(): void
    {
        $viernes = $this->proximoViernes();

        // El único barbero ya tiene cita 11:00–11:30
        $clienteExistente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);
        Cita::factory()->create([
            'tenant_id' => $this->tenant->id,
            'cliente_id' => $clienteExistente->id,
            'personal_id' => $this->barbero->id,
            'fecha' => $viernes->toDateString(),
            'hora_inicio' => '11:00:00',
            'hora_fin' => '11:30:00',
            'estado' => 'confirmada',
        ]);

        Livewire::test(ReservarPagina::class, ['slug' => 'demo-salon'])
            ->call('elegirServicio', $this->servicio->id)
            ->call('elegirFecha', $viernes->toDateString())
            ->call('elegirHora', '11:00')
            ->set('nombre', 'Max')
            ->set('apellido', 'Muster')
            ->set('telefono', '0170 1112233')
            ->call('reservar')
            ->assertHasErrors('hora');

        // Solo existe la cita original
        $this->assertSame(1, Cita::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_el_tenant_id_esta_bloqueado_contra_manipulacion(): void
    {
        $otroTenant = Tenant::factory()->create(['activo' => true]);

        // #[Locked] — Livewire rechaza la mutación desde el cliente
        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(ReservarPagina::class, ['slug' => 'demo-salon'])
            ->set('tenantId', $otroTenant->id);
    }
}
