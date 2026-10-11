<?php

namespace Tests\Feature;

use App\Filament\Widgets\BeliebtesteLeistungenWidget;
use App\Models\Cita;
use App\Models\Servicio;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widget "Beliebteste Leistungen": ranking de servicios por ingresos del mes
 * y aislamiento estricto por tenant (OWASP A01).
 */
class BeliebtesteLeistungenTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_servicios_y_aislamiento_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenantA->id);

        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => 'propietario']);

        $haarschnitt = Servicio::create(['tenant_id' => $tenantA->id, 'nombre' => 'Haarschnitt', 'precio' => 30]);
        $bart = Servicio::create(['tenant_id' => $tenantA->id, 'nombre' => 'Bartpflege', 'precio' => 20]);
        $fremd = Servicio::create(['tenant_id' => $tenantB->id, 'nombre' => 'FremdLeistung', 'precio' => 999]);

        // Haarschnitt: 2 × 30 € = 60 €; Bartpflege: 1 × 20 € = 20 €
        $this->venderServicio($tenantA, $haarschnitt, 30);
        $this->venderServicio($tenantA, $haarschnitt, 30);
        $this->venderServicio($tenantA, $bart, 20);
        // Tenant B: 999 € que NO debe verse
        $this->venderServicio($tenantB, $fremd, 999);

        $this->actingAs($user);

        Livewire::test(BeliebtesteLeistungenWidget::class)
            ->assertCanSeeTableRecords([$haarschnitt, $bart])
            ->assertCanNotSeeTableRecords([$fremd])
            ->assertSee('60,00')
            ->assertSee('20,00')
            ->assertDontSee('999');
    }

    private function venderServicio(Tenant $tenant, Servicio $servicio, float $precio): void
    {
        $cita = Cita::factory()->create([
            'tenant_id' => $tenant->id,
            'estado' => 'completada',
            'fecha' => today(),
        ]);

        DB::table('cita_servicios')->insert([
            'cita_id' => $cita->id,
            'servicio_id' => $servicio->id,
            'precio' => $precio,
            'duracion_minutos' => 30,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
