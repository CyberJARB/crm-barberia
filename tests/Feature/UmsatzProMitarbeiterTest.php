<?php

namespace Tests\Feature;

use App\Filament\Widgets\UmsatzProMitarbeiterWidget;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Pago;
use App\Models\Personal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widget "Umsatz pro Mitarbeiter": suma correcta por barbero y, sobre todo,
 * aislamiento estricto por tenant (OWASP A01) — no se filtra el ingreso de
 * barberos de otro negocio.
 */
class UmsatzProMitarbeiterTest extends TestCase
{
    use RefreshDatabase;

    public function test_suma_umsatz_por_barbero_y_aisla_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();
        app()->instance('current_tenant_id', $tenantA->id);

        $user = User::factory()->create(['tenant_id' => $tenantA->id, 'rol' => 'propietario']);

        $murat = Personal::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Murat', 'activo' => true]);
        $lena = Personal::factory()->create(['tenant_id' => $tenantA->id, 'nombre' => 'Lena', 'activo' => true]);
        $fremder = Personal::factory()->create(['tenant_id' => $tenantB->id, 'nombre' => 'FremdBarber', 'activo' => true]);

        // Murat: 100 + 50 = 150 €; Lena: 30 €; barbero de tenant B: 999 € (no debe verse)
        $this->registrarPago($tenantA, $murat, 100);
        $this->registrarPago($tenantA, $murat, 50);
        $this->registrarPago($tenantA, $lena, 30);
        $this->registrarPago($tenantB, $fremder, 999);

        $this->actingAs($user);

        Livewire::test(UmsatzProMitarbeiterWidget::class)
            ->assertCanSeeTableRecords([$murat, $lena])
            ->assertCanNotSeeTableRecords([$fremder])
            ->assertSee('150,00')      // Murat
            ->assertSee('30,00')       // Lena
            ->assertDontSee('999');    // ingreso de otro tenant NO se filtra
    }

    private function registrarPago(Tenant $tenant, Personal $personal, float $monto): void
    {
        $cliente = Cliente::factory()->create(['tenant_id' => $tenant->id]);

        $cita = Cita::factory()->create([
            'tenant_id' => $tenant->id,
            'personal_id' => $personal->id,
            'cliente_id' => $cliente->id,
            'fecha' => today(),
        ]);

        Pago::factory()->create([
            'tenant_id' => $tenant->id,
            'cita_id' => $cita->id,
            'cliente_id' => $cliente->id,
            'monto' => $monto,
        ]);
    }
}
