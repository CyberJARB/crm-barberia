<?php

namespace Tests\Feature;

use App\Filament\Widgets\OnboardingWidget;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\HorarioPersonal;
use App\Models\Personal;
use App\Models\Servicio;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Schnellstart (onboarding): el checklist guía al negocio nuevo y
 * desaparece al completar los pasos esenciales.
 */
class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->owner = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'propietario',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    private function completarTodo(): void
    {
        $servicio = Servicio::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Haarschnitt', 'duracion_minutos' => 30,
            'precio' => 25, 'activo' => true,
        ]);

        $personal = Personal::factory()->create(['tenant_id' => $this->tenant->id, 'activo' => true]);
        HorarioPersonal::create([
            'personal_id' => $personal->id, 'dia_semana' => 0,
            'hora_inicio' => '09:00', 'hora_fin' => '18:00', 'activo' => true,
        ]);

        $cliente = Cliente::factory()->create(['tenant_id' => $this->tenant->id]);
        Cita::factory()->create([
            'tenant_id' => $this->tenant->id, 'cliente_id' => $cliente->id,
            'fecha' => now()->addDays(3)->toDateString(),
            'hora_inicio' => '10:00:00', 'hora_fin' => '10:30:00',
            'estado' => 'pendiente',
        ]);
    }

    public function test_negocio_nuevo_ve_el_checklist_con_progreso_cero(): void
    {
        $this->actingAs($this->owner);

        $this->assertTrue(OnboardingWidget::canView());

        Livewire::actingAs($this->owner)
            ->test(OnboardingWidget::class)
            ->assertSee('Schnellstart')
            ->assertSee('0/4')
            ->assertSee('Dienstleistungen anlegen')
            ->assertSee('/reservar/'.$this->tenant->slug);
    }

    public function test_el_checklist_desaparece_al_completar_los_pasos(): void
    {
        $this->completarTodo();

        $this->actingAs($this->owner);

        $this->assertFalse(OnboardingWidget::canView());
    }

    public function test_recepcionista_no_ve_el_checklist(): void
    {
        $recepcion = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'rol' => 'recepcionista',
        ]);

        $this->actingAs($recepcion);

        $this->assertFalse(OnboardingWidget::canView());
    }

    public function test_el_progreso_avanza_por_pasos(): void
    {
        $this->actingAs($this->owner);

        Servicio::create([
            'tenant_id' => $this->tenant->id,
            'nombre' => 'Haarschnitt', 'duracion_minutos' => 30,
            'precio' => 25, 'activo' => true,
        ]);

        $pasos = OnboardingWidget::pasos();

        $this->assertTrue($pasos[0]['hecho']);   // servicios ✓
        $this->assertFalse($pasos[1]['hecho']);  // personal pendiente
        $this->assertFalse($pasos[2]['hecho']);  // citas pendiente
    }
}
