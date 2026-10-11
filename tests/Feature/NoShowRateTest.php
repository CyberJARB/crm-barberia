<?php

namespace Tests\Feature;

use App\Filament\Widgets\ResumenWidget;
use App\Models\Cita;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * KPI de no-shows en el dashboard: porcentaje correcto y aislamiento por
 * tenant (OWASP A01) — los no-shows de otro negocio no afectan al cálculo.
 */
class NoShowRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasa_no_show_correcta_y_aislada_por_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenantA->id);

        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => 'propietario']);

        // Tenant A: 4 citas este mes, 2 no-show → 50 %
        Cita::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'estado' => 'completada', 'fecha' => today()]);
        Cita::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'estado' => 'no_show', 'fecha' => today()]);

        // Tenant B: 5 no-shows que NO deben contar para A
        Cita::factory()->count(5)->create(['tenant_id' => $tenantB->id, 'estado' => 'no_show', 'fecha' => today()]);

        $this->actingAs($user);

        Livewire::test(ResumenWidget::class)
            ->assertSee('Nicht erschienen (Monat)')
            ->assertSee('2 (50%)');
    }
}
